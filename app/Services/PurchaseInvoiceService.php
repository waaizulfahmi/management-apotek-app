<?php

namespace App\Services;

use App\Models\Obat;
use App\Models\Outlet;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\OutletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class PurchaseInvoiceService
{
    /**
     * Hitung kalkulasi otomatis faktur pembelian (Frontend & Backend validation)
     */
    public function calculateTotals(array $headerData, array $itemsData): array
    {
        $calculatedItems = [];
        $subtotalGross = 0;
        $totalItemsNet = 0;
        $totalQuantityBase = 0;

        foreach ($itemsData as $item) {
            $qty = max(0, (float)($item['quantity'] ?? 0));
            $unitPrice = max(0, (float)($item['unit_price'] ?? 0));
            $conv = max(0.0001, (float)($item['conversion_factor'] ?? 1));
            $qtyBase = round($qty * $conv, 2);

            $itemSubtotal = round($qty * $unitPrice, 2);

            // Item Discount
            $discPercent = max(0, (float)($item['discount_percent'] ?? 0));
            $discAmount = max(0, (float)($item['discount_amount'] ?? 0));
            if ($discPercent > 0) {
                $discAmount = round($itemSubtotal * ($discPercent / 100), 2);
            }

            // Item Tax
            $taxPercent = max(0, (float)($item['tax_percent'] ?? 0));
            $taxAmount = max(0, (float)($item['tax_amount'] ?? 0));
            if ($taxPercent > 0) {
                $taxAmount = round(($itemSubtotal - $discAmount) * ($taxPercent / 100), 2);
            }

            $itemNet = round(($itemSubtotal - $discAmount) + $taxAmount, 2);

            $subtotalGross += $itemSubtotal;
            $totalItemsNet += $itemNet;
            $totalQuantityBase += $qtyBase;

            $calculatedItems[] = array_merge($item, [
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'conversion_factor' => $conv,
                'quantity_base' => $qtyBase,
                'discount_percent' => $discPercent,
                'discount_amount' => $discAmount,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'subtotal' => $itemSubtotal,
                'subtotal_net' => $itemNet,
            ]);
        }

        // Invoice Level Discount
        $discountType = $headerData['discount_type'] ?? 'nominal';
        $discountRate = max(0, (float)($headerData['discount_rate'] ?? 0));
        $discountAmount = max(0, (float)($headerData['discount_amount'] ?? 0));
        if ($discountType === 'percentage' && $discountRate > 0) {
            $discountAmount = round($totalItemsNet * ($discountRate / 100), 2);
        }

        $afterDiscount = max(0, $totalItemsNet - $discountAmount);

        // Invoice Level Tax
        $taxType = $headerData['tax_type'] ?? 'none';
        $taxRate = max(0, (float)($headerData['tax_rate'] ?? 0));
        $taxAmount = 0;

        if ($taxType === 'ppn_11') {
            $taxRate = 11;
            $taxAmount = round($afterDiscount * 0.11, 2);
        } elseif ($taxType === 'ppn_12') {
            $taxRate = 12;
            $taxAmount = round($afterDiscount * 0.12, 2);
        } elseif ($taxType === 'manual' || $taxType === 'custom') {
            $taxAmount = max(0, (float)($headerData['tax_amount'] ?? 0));
        }

        $shippingCost = max(0, (float)($headerData['shipping_cost'] ?? 0));
        $grandTotal = round($afterDiscount + $taxAmount + $shippingCost, 2);

        $paidAmount = max(0, (float)($headerData['paid_amount'] ?? 0));
        $remainingAmount = max(0, $grandTotal - $paidAmount);

        $paymentStatus = 'unpaid';
        if ($paidAmount >= $grandTotal && $grandTotal > 0) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount > 0) {
            $paymentStatus = 'partial';
        }

        return [
            'subtotal' => $subtotalGross,
            'total_items_net' => $totalItemsNet,
            'total_quantity_base' => $totalQuantityBase,
            'discount_type' => $discountType,
            'discount_rate' => $discountRate,
            'discount_amount' => $discountAmount,
            'tax_type' => $taxType,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'shipping_cost' => $shippingCost,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_status' => $paymentStatus,
            'items' => $calculatedItems,
        ];
    }

    /**
     * Generate unique internal receipt number (FP-YYYYMMDD-XXXX)
     */
    public function generateReceiptNumber(): string
    {
        $prefix = 'FP-' . date('Ymd') . '-';
        $last = DB::table('purchases')
            ->where('receipt_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->value('receipt_number');

        if ($last) {
            $parts = explode('-', $last);
            $seq = (int)end($parts) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Simpan Faktur (Draft / Baru)
     */
    public function saveInvoice(array $headerData, array $itemsData, ?int $id = null, ?User $user = null): Purchase
    {
        $totals = $this->calculateTotals($headerData, $itemsData);

        return DB::transaction(function () use ($headerData, $totals, $id, $user) {
            $userId = $user ? $user->id : (auth()->id() ?: 1);

            // Validasi duplikasi nomor faktur supplier untuk supplier yang sama
            $invoiceNumber = trim($headerData['invoice_number'] ?? '');
            $supplierId = (int)($headerData['supplier_id'] ?? 0);

            if (!empty($invoiceNumber) && $supplierId > 0) {
                $dupQuery = DB::table('purchases')
                    ->where('supplier_id', $supplierId)
                    ->where('invoice_number', $invoiceNumber)
                    ->whereNull('deleted_at');

                if ($id) {
                    $dupQuery->where('id', '!=', $id);
                }

                if ($dupQuery->exists()) {
                    throw new Exception("Nomor faktur \"{$invoiceNumber}\" sudah pernah tercatat untuk supplier/PBF ini. Harap periksa kembali.");
                }
            }

            $outletId = !empty($headerData['outlet_id']) ? (int)$headerData['outlet_id'] : OutletService::getActiveOutletId($user);

            $purchaseData = [
                'invoice_number' => $invoiceNumber ?: null,
                'receipt_number' => $headerData['receipt_number'] ?? $this->generateReceiptNumber(),
                'delivery_order_number' => $headerData['delivery_order_number'] ?? null,
                'purchase_order_id' => !empty($headerData['purchase_order_id']) ? (int)$headerData['purchase_order_id'] : null,
                'supplier_id' => $supplierId,
                'outlet_id' => $outletId,
                'user_id' => $userId,
                'purchase_date' => $headerData['purchase_date'] ?? now()->toDateString(),
                'received_date' => $headerData['received_date'] ?? now()->toDateString(),
                'due_date' => $headerData['due_date'] ?? now()->addDays(30)->toDateString(),
                'payment_type' => $headerData['payment_type'] ?? 'credit',
                'payment_status' => $totals['payment_status'],
                'subtotal' => $totals['subtotal'],
                'discount_type' => $totals['discount_type'],
                'discount_rate' => $totals['discount_rate'],
                'discount_amount' => $totals['discount_amount'],
                'tax_type' => $totals['tax_type'],
                'tax_rate' => $totals['tax_rate'],
                'tax_amount' => $totals['tax_amount'],
                'shipping_cost' => $totals['shipping_cost'],
                'grand_total' => $totals['grand_total'],
                'paid_amount' => $totals['paid_amount'],
                'remaining_amount' => $totals['remaining_amount'],
                'notes' => $headerData['notes'] ?? null,
                'document_path' => $headerData['document_path'] ?? null,
                'status' => 'DRAFT',
            ];

            if ($id) {
                $purchase = Purchase::where('id', $id)->lockForUpdate()->firstOrFail();
                if ($purchase->isFinal()) {
                    throw new Exception("Faktur sudah berstatus FINAL dan tidak dapat diedit langsung.");
                }
                $purchase->update($purchaseData);
                $purchase->items()->delete();
            } else {
                $purchaseData['po_number'] = $headerData['po_number'] ?? ('PO-' . date('YmdHis') . '-' . rand(1000, 9999));
                $purchase = Purchase::create($purchaseData);
            }

            foreach ($totals['items'] as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'medicine_id' => $item['medicine_id'],
                    'batch_number' => trim($item['batch_number'] ?? 'BATCH-' . date('Ymd')),
                    'expired_date' => $item['expired_date'] ?? now()->addYears(2)->toDateString(),
                    'quantity' => $item['quantity'],
                    'quantity_ordered' => $item['quantity'],
                    'quantity_received' => $item['quantity'],
                    'unit_id' => !empty($item['unit_id']) ? (int)$item['unit_id'] : null,
                    'unit_name' => $item['unit_name'] ?? null,
                    'conversion_factor' => $item['conversion_factor'],
                    'quantity_base' => $item['quantity_base'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'],
                    'discount_amount' => $item['discount_amount'],
                    'tax_percent' => $item['tax_percent'],
                    'tax_amount' => $item['tax_amount'],
                    'subtotal' => $item['subtotal'],
                    'subtotal_net' => $item['subtotal_net'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $purchase->fresh(['items.medicine', 'supplier', 'outlet']);
        });
    }

    /**
     * FINALISASI FAKTUR PEMBELIAN
     * Menambah stok obat di outlet, mencatat mutasi stok, FEFO batch, dan akun hutang supplier
     */
    public function finalizeInvoice(int $id, ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($id, $user) {
            $userId = $user ? $user->id : (auth()->id() ?: 1);

            $purchase = Purchase::where('id', $id)->lockForUpdate()->firstOrFail();

            // Idempotency check: pastikan belum pernah difinalisasi
            if ($purchase->isFinal()) {
                throw new Exception("Faktur ini sudah pernah difinalisasi sebelumnya.");
            }

            if ($purchase->isCancelled()) {
                throw new Exception("Faktur yang sudah dibatalkan tidak dapat difinalisasi.");
            }

            $items = $purchase->items()->get();
            if ($items->isEmpty()) {
                throw new Exception("Faktur tidak memiliki item obat untuk difinalisasi.");
            }

            $outletId = $purchase->outlet_id ?: OutletService::getActiveOutletId($user);
            $outlet = Outlet::find($outletId);
            $outletName = $outlet ? $outlet->name : "Outlet #{$outletId}";

            foreach ($items as $item) {
                // 1. Validasi keberadaan obat
                $medicine = Obat::where('kode', $item->medicine_id)->lockForUpdate()->first();
                if (!$medicine) {
                    throw new Exception("Obat dengan kode \"{$item->medicine_id}\" tidak ditemukan di master obat.");
                }

                if ($item->quantity <= 0) {
                    throw new Exception("Jumlah obat \"{$medicine->nama}\" harus lebih besar dari 0.");
                }

                if (empty($item->batch_number)) {
                    throw new Exception("Nomor batch untuk obat \"{$medicine->nama}\" wajib diisi sebelum finalisasi.");
                }

                if (empty($item->expired_date)) {
                    throw new Exception("Tanggal kedaluwarsa untuk obat \"{$medicine->nama}\" wajib diisi sebelum finalisasi.");
                }

                $qtyBase = (float)$item->quantity_base;
                if ($qtyBase <= 0) {
                    $conv = max(0.0001, (float)$item->conversion_factor);
                    $qtyBase = round((float)$item->quantity * $conv, 2);
                }

                // 2. Tambah stok fisik di outlet tujuan (product_stocks)
                $pStock = ProductStock::where('obat_id', $item->medicine_id)
                    ->where('outlet_id', $outletId)
                    ->lockForUpdate()
                    ->first();

                if (!$pStock) {
                    $pStock = ProductStock::create([
                        'obat_id' => $item->medicine_id,
                        'outlet_id' => $outletId,
                        'stock' => 0,
                        'min_stock' => $medicine->min_stok ?? 5,
                    ]);
                }

                $stockBefore = (float)$pStock->stock;
                $stockAfter = round($stockBefore + $qtyBase, 2);
                $pStock->stock = $stockAfter;
                $pStock->save();

                // 3. Pastikan relasi katalog outlet aktif (ProductOutlet)
                OutletService::attachProductToOutlet($item->medicine_id, $outletId);

                // 4. Tambah stok global master (obats.stok)
                $medicine->increment('stok', $qtyBase);

                // 5. Update harga beli master obat (dihitung per satuan dasar)
                $baseCostPerUnit = $item->conversion_factor > 0 
                    ? round((float)$item->unit_price / (float)$item->conversion_factor, 2)
                    : (float)$item->unit_price;

                if (Schema::hasColumn('obats', 'harga_beli')) {
                    $medicine->harga_beli = $baseCostPerUnit;
                    $medicine->save();
                }

                // 6. Simpan atau perbarui Batch FEFO di medicine_batches
                $existingBatch = DB::table('medicine_batches')
                    ->where('medicine_id', $item->medicine_id)
                    ->where('batch_number', $item->batch_number)
                    ->first();

                if ($existingBatch) {
                    DB::table('medicine_batches')
                        ->where('id', $existingBatch->id)
                        ->update([
                            'stock' => round((float)$existingBatch->stock + $qtyBase, 2),
                            'buy_price' => $baseCostPerUnit,
                            'expired_date' => $item->expired_date,
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);
                    $batchId = $existingBatch->id;
                } else {
                    $batchId = DB::table('medicine_batches')->insertGetId([
                        'medicine_id' => $item->medicine_id,
                        'batch_number' => $item->batch_number,
                        'expired_date' => $item->expired_date,
                        'stock' => $qtyBase,
                        'buy_price' => $baseCostPerUnit,
                        'sell_price' => round($baseCostPerUnit * 1.25, 2),
                        'supplier_id' => $purchase->supplier_id,
                        'received_date' => $purchase->received_date ?: now()->toDateString(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 7. Catat ke Kartu Stok & Histori Mutasi Persediaan (stock_movements)
                $movementNo = 'SM-' . date('Ymd') . '-' . rand(10000, 99999);
                $unitLabel = $item->unit_name ?: ($medicine->jenis_obat ?: 'Unit');
                $refNo = $purchase->invoice_number ?: $purchase->receipt_number;

                DB::table('stock_movements')->insert([
                    'movement_number' => $movementNo,
                    'outlet_id' => $outletId,
                    'medicine_id' => $item->medicine_id,
                    'batch_id' => $batchId,
                    'type' => 'in',
                    'movement_type' => 'PURCHASE_INVOICE',
                    'quantity' => (int)round($qtyBase),
                    'transaction_quantity' => (float)$item->quantity,
                    'unit_id' => $item->unit_id,
                    'unit_name' => $item->unit_name,
                    'conversion_to_base' => $item->conversion_factor,
                    'stock_before' => (int)round($stockBefore),
                    'stock_after' => (int)round($stockAfter),
                    'reference_number' => $refNo,
                    'user_id' => $userId,
                    'notes' => "Penerimaan Faktur Farmasi ({$item->quantity} {$unitLabel} = +{$qtyBase} stok dasar, Batch: {$item->batch_number}, Faktur: {$refNo})",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 8. Catat Supplier Price History jika tabel tersedia
                if (Schema::hasTable('supplier_price_histories')) {
                    DB::table('supplier_price_histories')->insert([
                        'supplier_id' => $purchase->supplier_id,
                        'medicine_id' => $item->medicine_id,
                        'price' => $item->unit_price,
                        'transaction_date' => $purchase->purchase_date ?: now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 9. Jika terhubung dengan Purchase Order (PO), update outstanding PO
            if ($purchase->purchase_order_id && Schema::hasTable('purchase_order_items')) {
                foreach ($items as $item) {
                    $poItem = DB::table('purchase_order_items')
                        ->where('purchase_order_id', $purchase->purchase_order_id)
                        ->where('medicine_id', $item->medicine_id)
                        ->first();

                    if ($poItem) {
                        $newReceived = (float)$poItem->received_quantity + (float)$item->quantity;
                        $newOutstanding = max(0, (float)$poItem->order_quantity - $newReceived);

                        DB::table('purchase_order_items')
                            ->where('id', $poItem->id)
                            ->update([
                                'received_quantity' => $newReceived,
                                'outstanding_quantity' => $newOutstanding,
                                'updated_at' => now(),
                            ]);
                    }
                }

                $totalOutstanding = DB::table('purchase_order_items')
                    ->where('purchase_order_id', $purchase->purchase_order_id)
                    ->sum('outstanding_quantity');

                $newPoStatus = $totalOutstanding <= 0 ? 'RECEIVED' : 'PARTIAL_RECEIVED';
                DB::table('purchase_orders')
                    ->where('id', $purchase->purchase_order_id)
                    ->update([
                        'status' => $newPoStatus,
                        'updated_at' => now(),
                    ]);
            }

            // 10. Catat Hutang Usaha Supplier di Modul Keuangan (accounts_payables)
            if (Schema::hasTable('accounts_payables')) {
                $remAmount = (float)$purchase->remaining_amount;
                if ($remAmount > 0 || in_array($purchase->payment_type, ['credit', 'termin'])) {
                    $existingAp = DB::table('accounts_payables')
                        ->where('purchase_id', $purchase->id)
                        ->first();

                    if ($existingAp) {
                        $payableNumber = $existingAp->payable_number;
                    } else {
                        $apPrefix = 'AP-' . date('Ymd') . '-';
                        $lastAp = DB::table('accounts_payables')
                            ->where('payable_number', 'like', $apPrefix . '%')
                            ->orderBy('id', 'desc')
                            ->value('payable_number');
                        $apSeq = 1;
                        if ($lastAp) {
                            $parts = explode('-', $lastAp);
                            $apSeq = (int)end($parts) + 1;
                        }
                        $payableNumber = $apPrefix . str_pad($apSeq, 4, '0', STR_PAD_LEFT);
                    }

                    $apData = [
                        'payable_number' => $payableNumber,
                        'purchase_id' => $purchase->id,
                        'supplier_id' => $purchase->supplier_id,
                        'invoice_number' => $purchase->invoice_number ?: $purchase->receipt_number,
                        'total_amount' => $purchase->grand_total,
                        'paid_amount' => $purchase->paid_amount,
                        'remaining_amount' => $purchase->remaining_amount,
                        'due_date' => $purchase->due_date ?: now()->addDays(30)->toDateString(),
                        'status' => $purchase->payment_status === 'paid' ? 'paid' : ($purchase->payment_status === 'partial' ? 'partial' : 'unpaid'),
                        'updated_at' => now(),
                    ];

                    if ($existingAp) {
                        DB::table('accounts_payables')->where('id', $existingAp->id)->update($apData);
                    } else {
                        $apData['created_at'] = now();
                        DB::table('accounts_payables')->insert($apData);
                    }
                }
            }

            // 11. Update Status Faktur menjadi FINAL
            $purchase->status = 'FINAL';
            $purchase->finalized_at = now();
            $purchase->finalized_by = $userId;
            $purchase->save();

            // 12. Audit Log
            if (Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'user_id' => $userId,
                    'action' => 'FINALIZE_PURCHASE_INVOICE',
                    'module' => 'PurchaseInvoices',
                    'record_id' => $purchase->id,
                    'new_values' => json_encode([
                        'invoice_number' => $purchase->invoice_number,
                        'receipt_number' => $purchase->receipt_number,
                        'grand_total' => $purchase->grand_total,
                        'outlet_id' => $outletId,
                        'outlet_name' => $outletName,
                        'items_count' => $items->count(),
                    ]),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $purchase->fresh(['items.medicine', 'supplier', 'outlet', 'finalizedBy']);
        });
    }

    /**
     * PEMBATALAN FAKTUR FINAL (REVERSAL MUTASI STOK)
     * Mengurangi kembali stok yang sudah masuk dengan transaksi pembalik sah
     */
    public function cancelInvoice(int $id, string $reason, ?User $user = null): Purchase
    {
        return DB::transaction(function () use ($id, $reason, $user) {
            $userId = $user ? $user->id : (auth()->id() ?: 1);

            $purchase = Purchase::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($purchase->isCancelled()) {
                throw new Exception("Faktur ini sudah dibatalkan sebelumnya.");
            }

            // Jika faktur masih DRAFT, cukup tandai CANCELLED tanpa mutasi stok
            if ($purchase->isDraft()) {
                $purchase->status = 'CANCELLED';
                $purchase->cancelled_at = now();
                $purchase->cancelled_by = $userId;
                $purchase->cancellation_reason = $reason;
                $purchase->save();
                return $purchase;
            }

            // Jika faktur sudah FINAL, lakukan pengecekan & reversal stok
            $items = $purchase->items()->get();
            $outletId = $purchase->outlet_id ?: OutletService::getActiveOutletId($user);

            foreach ($items as $item) {
                $medicine = Obat::where('kode', $item->medicine_id)->lockForUpdate()->first();
                $qtyBase = (float)$item->quantity_base;

                $pStock = ProductStock::where('obat_id', $item->medicine_id)
                    ->where('outlet_id', $outletId)
                    ->lockForUpdate()
                    ->first();

                $currentStock = $pStock ? (float)$pStock->stock : 0;
                if ($currentStock < $qtyBase) {
                    $medName = $medicine ? $medicine->nama : $item->medicine_id;
                    throw new Exception("Tidak dapat membatalkan faktur: Stok \"{$medName}\" di outlet ({$currentStock}) lebih kecil dari jumlah masuk faktur ({$qtyBase}) karena sebagian sudah terjual atau terpakai.");
                }

                // Kurangi stok outlet
                $stockBefore = $currentStock;
                $stockAfter = round($currentStock - $qtyBase, 2);
                $pStock->stock = $stockAfter;
                $pStock->save();

                // Kurangi stok global
                if ($medicine) {
                    $medicine->decrement('stok', $qtyBase);
                }

                // Kurangi stok batch
                DB::table('medicine_batches')
                    ->where('medicine_id', $item->medicine_id)
                    ->where('batch_number', $item->batch_number)
                    ->decrement('stock', $qtyBase);

                // Catat mutasi pembalik (Reversal Out)
                $movementNo = 'REV-' . date('Ymd') . '-' . rand(10000, 99999);
                $refNo = $purchase->invoice_number ?: $purchase->receipt_number;

                DB::table('stock_movements')->insert([
                    'movement_number' => $movementNo,
                    'outlet_id' => $outletId,
                    'medicine_id' => $item->medicine_id,
                    'type' => 'out',
                    'movement_type' => 'PURCHASE_CANCELLATION_REVERSAL',
                    'quantity' => (int)round($qtyBase),
                    'transaction_quantity' => (float)$item->quantity,
                    'unit_id' => $item->unit_id,
                    'unit_name' => $item->unit_name,
                    'conversion_to_base' => $item->conversion_factor,
                    'stock_before' => (int)round($stockBefore),
                    'stock_after' => (int)round($stockAfter),
                    'reference_number' => $refNo,
                    'user_id' => $userId,
                    'notes' => "Reversal Pembatalan Faktur: {$reason} (Faktur: {$refNo})",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Batalkan akun hutang supplier
            if (Schema::hasTable('accounts_payables')) {
                DB::table('accounts_payables')
                    ->where('purchase_id', $purchase->id)
                    ->update([
                        'status' => 'cancelled',
                        'remaining_amount' => 0,
                        'updated_at' => now(),
                    ]);
            }

            // Update status faktur
            $purchase->status = 'CANCELLED';
            $purchase->cancelled_at = now();
            $purchase->cancelled_by = $userId;
            $purchase->cancellation_reason = $reason;
            $purchase->save();

            // Audit Log
            if (Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'user_id' => $userId,
                    'action' => 'CANCEL_PURCHASE_INVOICE',
                    'module' => 'PurchaseInvoices',
                    'record_id' => $purchase->id,
                    'new_values' => json_encode([
                        'invoice_number' => $purchase->invoice_number,
                        'reason' => $reason,
                        'grand_total' => $purchase->grand_total,
                    ]),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $purchase->fresh(['items.medicine', 'supplier', 'outlet', 'cancelledBy']);
        });
    }
}
