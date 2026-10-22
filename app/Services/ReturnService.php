<?php

namespace App\Services;

use App\Models\ProductReturn;
use App\Models\ProductReturnItem;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Obat;
use App\Models\MedicineBatch;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Exception;

class ReturnService
{
    /**
     * Create a new return record
     */
    public function createReturn(string $type, int $referenceId, array $data, int $userId)
    {
        return DB::transaction(function () use ($type, $referenceId, $data, $userId) {
            $refModel = $type === 'sale' ? Sale::findOrFail($referenceId) : Purchase::findOrFail($referenceId);
            
            $returnNumber = $this->generateReturnNumber($type);
            
            $productReturn = ProductReturn::create([
                'return_number' => $returnNumber,
                'type' => $type,
                'reference_id' => $referenceId,
                'outlet_id' => $refModel->outlet_id ?? \App\Services\OutletService::getActiveOutletId(),
                'customer_id' => $type === 'sale' ? $refModel->customer_id : null,
                'supplier_id' => $type === 'purchase' ? $refModel->supplier_id : null,
                'user_id' => $userId,
                'status' => 'PENDING',
                'total_amount' => $data['total_amount'],
                'refund_method' => $data['refund_method'] ?? null,
                'refund_amount' => $data['refund_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                // Validate quantity
                $this->validateReturnQuantity($type, $referenceId, $item['medicine_id'], $item['quantity']);

                ProductReturnItem::create([
                    'product_return_id' => $productReturn->id,
                    'medicine_id' => $item['medicine_id'],
                    'batch_id' => $item['batch_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                    'reason' => $item['reason'],
                    'condition' => $item['condition'] ?? null,
                ]);
            }

            return $productReturn;
        });
    }

    /**
     * Approve a return
     */
    public function approveReturn(int $returnId, int $userId)
    {
        return DB::transaction(function () use ($returnId, $userId) {
            $productReturn = ProductReturn::with('items')->findOrFail($returnId);

            if ($productReturn->status !== 'PENDING') {
                throw new Exception('Hanya retur berstatus PENDING yang dapat disetujui.');
            }

            // Process Stock & Finance
            if ($productReturn->type === 'sale') {
                $this->processSaleReturn($productReturn, $userId);
            } else {
                $this->processPurchaseReturn($productReturn, $userId);
            }

            $productReturn->update([
                'status' => 'APPROVED',
                'approved_by' => $userId,
            ]);

            return $productReturn;
        });
    }

    /**
     * Process Sales Return (Restock if Good condition, adjust finance & loyalty points)
     */
    private function processSaleReturn(ProductReturn $productReturn, int $userId)
    {
        $outletId = $productReturn->outlet_id ?: ($productReturn->sale ? $productReturn->sale->outlet_id : null);

        foreach ($productReturn->items as $item) {
            $medicine = Obat::where('kode', $item->medicine_id)->first();
            if (!$medicine) continue;

            $pStock = null;
            if ($outletId && \Illuminate\Support\Facades\Schema::hasTable('product_stocks')) {
                $pStock = \App\Models\ProductStock::firstOrCreate(
                    ['obat_id' => $item->medicine_id, 'outlet_id' => $outletId],
                    ['stock' => 0, 'min_stock' => 5]
                );
                $outletStockBefore = (float)$pStock->stock;
            } else {
                $outletStockBefore = (float)$medicine->stok;
            }

            // If condition is 'Baik', return to active sellable stock
            if ($item->condition === 'Baik') {
                if ($item->batch_id) {
                    $batch = MedicineBatch::find($item->batch_id);
                    if ($batch) {
                        $batch->increment('stock', $item->quantity);
                    }
                }
                $medicine->increment('stok', $item->quantity);

                // Update outlet stock in product_stocks table
                if ($pStock) {
                    $pStock->increment('stock', $item->quantity);
                    $outletStockAfter = (float)$pStock->fresh()->stock;
                } else {
                    $outletStockAfter = (float)$medicine->fresh()->stok;
                }

                $this->logStockMovement(
                    $item,
                    'in',
                    'SALE_RETURN',
                    'RETUR PENJUALAN',
                    $productReturn->return_number,
                    $outletStockBefore,
                    $outletStockAfter,
                    $userId,
                    $outletId
                );
            } else {
                // Damaged return: does NOT increase sellable active stock
                $outletStockAfter = $outletStockBefore;
                $this->logStockMovement(
                    $item,
                    'return',
                    'SALE_RETURN',
                    'RETUR PENJUALAN (RUSAK)',
                    $productReturn->return_number,
                    $outletStockBefore,
                    $outletStockAfter,
                    $userId,
                    $outletId
                );
            }
        }

        // Process Finance (Refunds) & Update Shift Metrics
        if ($productReturn->refund_amount > 0 && $productReturn->refund_method === 'Cash') {
            $this->processRefundFinance($productReturn, 'sale', $userId);
        }

        if ($productReturn->sale && $productReturn->sale->shift_id) {
            app(ShiftService::class)->recalculateShiftMetrics($productReturn->sale->shift_id);
        }

        // Reverse loyalty points for returned sale if applicable
        if ($productReturn->customer_id && $productReturn->total_amount > 0) {
            $customer = Customer::find($productReturn->customer_id);
            if ($customer) {
                $pointService = app(PointService::class);
                $pointsToReverse = $pointService->calculatePotentialPoints($customer, (float)$productReturn->total_amount);
                if ($pointsToReverse > 0) {
                    $pointService->reversePoints(
                        $customer,
                        $pointsToReverse,
                        "Pembatalan/Retur Transaksi Retur #{$productReturn->return_number}",
                        $productReturn->return_number
                    );
                }
            }
        }
    }

    /**
     * Process Purchase Return (Deduct stock, adjust finance)
     */
    private function processPurchaseReturn(ProductReturn $productReturn, int $userId)
    {
        $outletId = $productReturn->outlet_id ?: ($productReturn->purchase ? $productReturn->purchase->outlet_id : null);

        foreach ($productReturn->items as $item) {
            $medicine = Obat::where('kode', $item->medicine_id)->first();
            if (!$medicine) continue;

            $pStock = null;
            if ($outletId && \Illuminate\Support\Facades\Schema::hasTable('product_stocks')) {
                $pStock = \App\Models\ProductStock::where('obat_id', $item->medicine_id)
                    ->where('outlet_id', $outletId)
                    ->first();
                $outletStockBefore = $pStock ? (float)$pStock->stock : (float)$medicine->stok;
            } else {
                $outletStockBefore = (float)$medicine->stok;
            }

            if ($item->batch_id) {
                $batch = MedicineBatch::find($item->batch_id);
                if ($batch) {
                    if ($batch->stock < $item->quantity) {
                        throw new Exception("Stok untuk batch ini tidak mencukupi untuk diretur.");
                    }
                    $batch->decrement('stock', $item->quantity);
                }
            }
            $medicine->decrement('stok', $item->quantity);

            // Deduct from outlet stock
            if ($pStock) {
                $pStock->decrement('stock', $item->quantity);
                $outletStockAfter = (float)$pStock->fresh()->stock;
            } else {
                $outletStockAfter = (float)$medicine->fresh()->stok;
            }

            $this->logStockMovement(
                $item,
                'out',
                'PURCHASE_RETURN',
                'RETUR PEMBELIAN',
                $productReturn->return_number,
                $outletStockBefore,
                $outletStockAfter,
                $userId,
                $outletId
            );
        }

        // Process Finance (Refunds / Receivables)
        if ($productReturn->refund_amount > 0 && $productReturn->refund_method === 'Cash') {
            $this->processRefundFinance($productReturn, 'purchase', $userId);
        }
    }

    /**
     * Process Finance logic for Refunds
     */
    private function processRefundFinance(ProductReturn $productReturn, string $type, int $userId)
    {
        $cashAccount = DB::table('cash_bank_accounts')->where('type', 'cash')->first();
        if (!$cashAccount) return;

        $journalNo = 'JRN-RET-' . date('Ymd') . '-' . rand(1000, 9999);
        $journalId = DB::table('journal_entries')->insertGetId([
            'journal_number' => $journalNo,
            'transaction_date' => now(),
            'reference_number' => $productReturn->return_number,
            'notes' => $type === 'sale' ? "Refund Retur Penjualan: {$productReturn->return_number}" : "Penerimaan Retur Pembelian: {$productReturn->return_number}",
            'posted_by' => $userId,
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($type === 'sale') {
            // Money goes out (Refund to customer)
            DB::table('cash_bank_accounts')->where('id', $cashAccount->id)->decrement('current_balance', $productReturn->refund_amount);
            
            // Debit: Sales Return, Credit: Cash
            $salesReturnCoa = DB::table('chart_of_accounts')->where('code', '4120')->value('id') ?? DB::table('chart_of_accounts')->where('code', '4100')->value('id');
            $cashCoa = $cashAccount->coa_id;
            
            if ($salesReturnCoa) {
                DB::table('journal_entry_lines')->insert([
                    ['journal_entry_id' => $journalId, 'coa_id' => $salesReturnCoa, 'debit' => $productReturn->refund_amount, 'credit' => 0, 'memo' => 'Retur Penjualan', 'created_at' => now(), 'updated_at' => now()],
                    ['journal_entry_id' => $journalId, 'coa_id' => $cashCoa, 'debit' => 0, 'credit' => $productReturn->refund_amount, 'memo' => 'Kas Keluar (Refund)', 'created_at' => now(), 'updated_at' => now()]
                ]);
            }
        } else {
            // Money comes in (Refund from supplier)
            DB::table('cash_bank_accounts')->where('id', $cashAccount->id)->increment('current_balance', $productReturn->refund_amount);
            
            // Debit: Cash, Credit: Purchase Return
            $purchaseReturnCoa = DB::table('chart_of_accounts')->where('code', '5120')->value('id') ?? DB::table('chart_of_accounts')->where('code', '5100')->value('id');
            $cashCoa = $cashAccount->coa_id;

            if ($purchaseReturnCoa) {
                DB::table('journal_entry_lines')->insert([
                    ['journal_entry_id' => $journalId, 'coa_id' => $cashCoa, 'debit' => $productReturn->refund_amount, 'credit' => 0, 'memo' => 'Kas Masuk (Refund Pembelian)', 'created_at' => now(), 'updated_at' => now()],
                    ['journal_entry_id' => $journalId, 'coa_id' => $purchaseReturnCoa, 'debit' => 0, 'credit' => $productReturn->refund_amount, 'memo' => 'Retur Pembelian', 'created_at' => now(), 'updated_at' => now()]
                ]);
            }
        }
    }

    /**
     * Helper to log stock movement
     */
    private function logStockMovement($item, $type, $movementType, $notes, $refNo, $before, $after, $userId, ?int $outletId = null)
    {
        $smNo = 'SM-' . date('Ymd') . '-' . rand(10000, 99999);
        
        DB::table('stock_movements')->insert([
            'movement_number' => $smNo,
            'outlet_id' => $outletId,
            'medicine_id' => $item->medicine_id,
            'batch_id' => $item->batch_id,
            'batch_number' => $item->batch->batch_number ?? 'BATCH-SYS',
            'expired_date' => $item->batch->expired_date ?? now()->toDateString(),
            'type' => $type,
            'movement_type' => $movementType,
            'quantity' => $item->quantity,
            'stock_before' => $before,
            'stock_after' => $after,
            'reference_number' => $refNo,
            'user_id' => $userId,
            'notes' => $notes,
            'status' => 'POSTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function generateReturnNumber(string $type)
    {
        $prefix = $type === 'sale' ? 'RET-S' : 'RET-P';
        $year = date('Y');
        
        $lastReturn = ProductReturn::where('type', $type)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();
            
        $number = 1;
        if ($lastReturn) {
            $lastNumber = intval(substr($lastReturn->return_number, -6));
            $number = $lastNumber + 1;
        }
        
        return sprintf("%s-%s-%06d", $prefix, $year, $number);
    }

    private function validateReturnQuantity(string $type, int $referenceId, string $medicineId, int $requestQty)
    {
        if ($type === 'sale') {
            $boughtQty = (int) \App\Models\SaleItem::where('sale_id', $referenceId)
                ->where('medicine_id', $medicineId)
                ->sum('quantity');
        } else {
            $pItems = \App\Models\PurchaseItem::where('purchase_id', $referenceId)
                ->where('medicine_id', $medicineId)
                ->get();
            $receivedSum = $pItems->sum('quantity_received');
            $orderedSum = $pItems->sum('quantity_ordered');
            $boughtQty = (int) ($receivedSum > 0 ? $receivedSum : $orderedSum);
        }

        if ($boughtQty <= 0) {
            throw new Exception("Barang tidak ditemukan dalam transaksi asli.");
        }
        
        $alreadyReturned = ProductReturnItem::whereHas('productReturn', function ($q) use ($referenceId, $type) {
                $q->where('reference_id', $referenceId)
                  ->where('type', $type)
                  ->where('status', '!=', 'CANCELLED');
            })
            ->where('medicine_id', $medicineId)
            ->sum('quantity');

        $maxReturn = $boughtQty - $alreadyReturned;

        if ($requestQty > $maxReturn) {
            throw new Exception("Kuantitas retur melebihi batas. Maksimal yang bisa diretur: {$maxReturn}");
        }
    }
}
