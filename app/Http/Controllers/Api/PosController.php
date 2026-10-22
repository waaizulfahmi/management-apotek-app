<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FefoService;
use Inertia\Inertia;
use Exception;

class PosController extends Controller
{
    /**
     * Display POS Index Page via Inertia (Fast Paginated)
     */
    public function index(Request $request)
    {
        $paginatedMedicines = $this->getOptimizedMedicines(
            $request->input('search'),
            $request->input('category'),
            10,
            (int) $request->input('page', 1)
        );

        $categories = DB::table('obats')
            ->whereNull('deleted_at')
            ->whereNotNull('kategori')
            ->where('kategori', '!=', '')
            ->distinct()
            ->pluck('kategori');

        $customers = DB::table('customers')
            ->select('id', 'code', 'name', 'phone', 'membership_level', 'points', 'allergies', 'medical_notes')
            ->where('is_active', true)
            ->get();

        $prescriptions = DB::table('prescriptions')
            ->leftJoin('customers', 'prescriptions.customer_id', '=', 'customers.id')
            ->leftJoin('doctors', 'prescriptions.doctor_id', '=', 'doctors.id')
            ->select(
                'prescriptions.*',
                'customers.name as patient_name',
                'doctors.name as doctor_name'
            )
            ->whereIn('prescriptions.status', ['created', 'verified'])
            ->orderBy('prescriptions.id', 'desc')
            ->get();

        foreach ($prescriptions as $rx) {
            $rx->items = DB::table('prescription_items')
                ->join('obats', 'prescription_items.medicine_id', '=', 'obats.kode')
                ->select(
                    'prescription_items.*',
                    'obats.nama as medicine_name',
                    'obats.harga as unit_price',
                    'obats.stok as stock',
                    'obats.jenis_obat as unit'
                )
                ->where('prescription_items.prescription_id', $rx->id)
                ->get();
        }

        $masterShifts = \App\Models\MasterShift::where('is_active', true)->orderBy('start_time', 'asc')->get();
        $activeOutletId = \App\Services\OutletService::getActiveOutletId();

        return Inertia::render('Pos/Index', [
            'medicines' => $paginatedMedicines['data'],
            'pagination' => $paginatedMedicines,
            'categories' => $categories,
            'customers' => $customers,
            'prescriptions' => $prescriptions,
            'masterShifts' => $masterShifts,
            'activeOutletId' => $activeOutletId,
        ]);
    }

    /**
     * JSON Endpoint for Live Search & Pagination in POS
     */
    public function searchMedicines(Request $request)
    {
        $paginated = $this->getOptimizedMedicines(
            $request->input('search'),
            $request->input('category'),
            (int) $request->input('per_page', 10),
            (int) $request->input('page', 1)
        );

        return response()->json($paginated);
    }

    /**
     * Helper to fetch medicines with batch-loaded unit prices and pagination metadata
     */
    private function getOptimizedMedicines(?string $search = null, ?string $category = null, int $perPage = 10, int $page = 1)
    {
        $activeOutletId = \App\Services\OutletService::getActiveOutletId();

        $query = DB::table('obats')
            ->join('product_outlets', function ($join) use ($activeOutletId) {
                $join->on('obats.kode', '=', 'product_outlets.obat_id')
                     ->where('product_outlets.outlet_id', '=', $activeOutletId)
                     ->where('product_outlets.is_active', '=', true)
                     ->whereNull('product_outlets.deleted_at');
            })
            ->leftJoin('product_stocks', function ($join) use ($activeOutletId) {
                $join->on('obats.kode', '=', 'product_stocks.obat_id')
                     ->where('product_stocks.outlet_id', '=', $activeOutletId);
            })
            ->select(
                'obats.kode',
                'obats.nama',
                'obats.gambar',
                'obats.jenis_obat',
                'obats.kategori',
                DB::raw('COALESCE(product_outlets.price, obats.harga) as harga'),
                DB::raw('COALESCE(product_stocks.stock, obats.stok, 0) as stok'),
                'obats.satuan_dasar_id',
                'obats.satuan_pembelian_id',
                'obats.satuan_penjualan_id'
            )
            ->whereNull('obats.deleted_at');

        if ($search) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('obats.nama', 'like', "%{$term}%")
                  ->orWhere('obats.kode', 'like', "%{$term}%")
                  ->orWhere('obats.merk', 'like', "%{$term}%");
            });
        }

        if ($category) {
            $query->where('obats.kategori', $category);
        }

        $total = $query->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = max(1, min($page, $lastPage));

        $offset = ($currentPage - 1) * $perPage;

        // Prioritize items with stock > 0 first, then order by name
        $medicines = $query->orderByRaw('COALESCE(product_stocks.stock, 0) > 0 DESC')
            ->orderBy('obats.nama', 'asc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        if ($medicines->isNotEmpty()) {
            $kodes = $medicines->pluck('kode')->toArray();

            // 1 Batch query for all product units
            $unitsByProduct = DB::table('product_units')
                ->join('units', 'product_units.unit_id', '=', 'units.id')
                ->whereIn('product_units.product_id', $kodes)
                ->select('product_units.*', 'units.name as unit_name')
                ->get()
                ->groupBy('product_id');

            // 1 Batch query for all selling prices
            $pricesByProduct = DB::table('product_prices')
                ->whereIn('product_id', $kodes)
                ->where('price_type', 'SELLING')
                ->get()
                ->groupBy('product_id');

            foreach ($medicines as $med) {
                $prodUnits = $unitsByProduct->get($med->kode, collect());
                $prodPrices = $pricesByProduct->get($med->kode, collect());

                $unitPrices = [];
                foreach ($prodUnits as $pu) {
                    $priceObj = $prodPrices->firstWhere('unit_id', $pu->unit_id);
                    $unitPrices[] = [
                        'unit_id' => $pu->unit_id,
                        'unit_name' => $pu->unit_name,
                        'is_selling_unit' => (bool)$pu->is_selling_unit,
                        'selling_price' => $priceObj ? (float)$priceObj->price : (float)$med->harga,
                    ];
                }

                $med->units = $unitPrices;
                $med->satuan_dasar = $med->jenis_obat;
            }
        }

        return [
            'data' => $medicines->values()->toArray(),
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ];
    }

    /**
     * Process POS Checkout (Atomic Transaction)
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.kode' => 'required|exists:obats,kode',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_id' => 'nullable|exists:units,id',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|exists:customers,id',
            'prescription_id' => 'nullable|exists:prescriptions,id',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,debit,credit,qris,transfer,e-wallet,mixed',
            'paid_amount' => 'required|numeric|min:0',
        ]);

        $idempotencyKey = $request->header('X-Idempotency-Key') ?: $request->input('client_tx_id');
        if ($idempotencyKey) {
            $existingInvoice = \Illuminate\Support\Facades\Cache::get('pos_idempotency_' . $idempotencyKey);
            if ($existingInvoice) {
                $sale = DB::table('sales')->where('invoice_number', $existingInvoice)->first();
                if ($sale) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Transaksi POS Berhasil Diproses! (Idempotent)',
                        'data' => [
                            'sale_id' => $sale->id,
                            'invoice_number' => $sale->invoice_number,
                            'grand_total' => $sale->grand_total,
                            'paid_amount' => $sale->paid_amount,
                            'change_amount' => $sale->change_amount,
                        ]
                    ]);
                }
            }
        }

        $conversionService = app(\App\Services\UnitConversionService::class);

        DB::beginTransaction();
        try {
            $invoiceNumber = 'INV-' . date('YmdHis') . '-' . rand(100, 999);
            $cashierId = auth()->id();

            // Validate active shift for cashier
            $shiftService = new \App\Services\ShiftService();
            $activeShift = $shiftService->getActiveShift($cashierId);
            if (!$activeShift) {
                throw new Exception("Shift belum dibuka. Silakan buka shift terlebih dahulu.");
            }

            // Ensure outlet_id is accurately resolved and synced
            $outletId = $activeShift->outlet_id ?: (auth()->user()->outlet_id ?: \App\Services\OutletService::getActiveOutletId(auth()->user()));
            if (!$activeShift->outlet_id && $outletId) {
                $activeShift->update(['outlet_id' => $outletId]);
            }

            $subtotal = 0;
            $itemsToInsert = [];

            foreach ($request->items as $item) {
                $medKode = $item['kode'];
                $txQty = (int)$item['quantity'];
                $unitId = isset($item['unit_id']) ? (int)$item['unit_id'] : null;

                // Validate product is active and attached to this outlet
                if (!\App\Services\OutletService::isProductAvailableInOutlet($medKode, $outletId)) {
                    $medName = DB::table('obats')->where('kode', $medKode)->value('nama') ?? $medKode;
                    throw new Exception("Produk '{$medName}' ({$medKode}) tidak terdaftar atau tidak aktif di outlet ini.");
                }

                $unitName = null;
                $conversionFactor = 1.0000;

                // Prevent price manipulation: resolve price strictly from backend
                $backendPrice = \App\Services\OutletService::getOutletPrice($medKode, $outletId);
                if ($unitId) {
                    $uObj = \App\Models\Unit::find($unitId);
                    if ($uObj) $unitName = $uObj->name;
                    $conversionFactor = $conversionService->getConversionFactor($medKode, $unitId);

                    $unitPriceRecord = DB::table('product_prices')
                        ->where('product_id', $medKode)
                        ->where('unit_id', $unitId)
                        ->where('price_type', 'SELLING')
                        ->value('price');
                    if ($unitPriceRecord && (float)$unitPriceRecord > 0) {
                        $backendPrice = (float)$unitPriceRecord;
                    }
                }
                $sellingUnitPrice = $backendPrice;

                // Total base stock to deduct from FEFO batches
                $baseQtyNeeded = (int)ceil($txQty * $conversionFactor);

                // FEFO Stock Deduction (including outlet stock)
                $allocatedBatches = FefoService::deductStock(
                    $medKode,
                    $baseQtyNeeded,
                    $cashierId,
                    $invoiceNumber,
                    $unitId,
                    $unitName,
                    $txQty,
                    $conversionFactor,
                    $outletId
                );

                // Line subtotal based on backend verified unit price
                $lineSubtotal = round($txQty * $sellingUnitPrice, 2);
                $subtotal += $lineSubtotal;

                $totalBatches = count($allocatedBatches);
                $accumulatedSubtotal = 0;
                $accumulatedQty = 0;

                foreach ($allocatedBatches as $idx => $allocated) {
                    $batchBaseQty = (float)$allocated['quantity'];
                    $batchTxQty = $conversionFactor > 0 ? round($batchBaseQty / $conversionFactor, 4) : $batchBaseQty;

                    if ($idx === $totalBatches - 1) {
                        $itemBatchQty = max(1, $txQty - $accumulatedQty);
                        $itemBatchSubtotal = max(0, round($lineSubtotal - $accumulatedSubtotal, 2));
                    } else {
                        $itemBatchQty = (int)round($batchTxQty);
                        $itemBatchSubtotal = round($itemBatchQty * $sellingUnitPrice, 2);
                        $accumulatedQty += $itemBatchQty;
                        $accumulatedSubtotal += $itemBatchSubtotal;
                    }

                    $itemsToInsert[] = [
                        'medicine_id' => $medKode,
                        'batch_id' => $allocated['batch_id'],
                        'quantity' => $itemBatchQty,
                        'unit_id' => $unitId,
                        'unit_name' => $unitName,
                        'conversion_to_base' => $conversionFactor,
                        'quantity_base' => $batchBaseQty,
                        'buy_price' => $allocated['buy_price'],
                        'unit_price' => $sellingUnitPrice,
                        'discount' => 0,
                        'subtotal' => $itemBatchSubtotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            $discount = (float)($request->discount ?? 0);
            if ($discount > 0 && !auth()->user()->can('pos.discount') && !in_array(auth()->user()->role, ['admin', 'owner'])) {
                throw new Exception("Anda tidak memiliki izin untuk memberikan diskon transaksi.");
            }
            $tax = (float)($request->tax ?? 0);
            $grandTotal = ($subtotal - $discount) + $tax;

            if ($request->paid_amount < $grandTotal) {
                throw new Exception("Jumlah pembayaran (Rp " . number_format($request->paid_amount) . ") kurang dari Grand Total (Rp " . number_format($grandTotal) . ")");
            }

            $changeAmount = $request->paid_amount - $grandTotal;

            // Insert Sale Record with shift_id & outlet_id
            $saleId = DB::table('sales')->insertGetId([
                'invoice_number' => $invoiceNumber,
                'cashier_id' => $cashierId,
                'shift_id' => $activeShift->id,
                'outlet_id' => $outletId,
                'customer_id' => $request->customer_id,
                'prescription_id' => $request->prescription_id ?: null,
                'sale_date' => now(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'grand_total' => $grandTotal,
                'paid_amount' => $request->paid_amount,
                'change_amount' => $changeAmount,
                'payment_method' => $request->payment_method,
                'status' => 'completed',
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update prescription status to dispensed if linked
            if ($request->prescription_id) {
                DB::table('prescriptions')->where('id', $request->prescription_id)->update([
                    'status' => 'dispensed',
                    'updated_at' => now(),
                ]);
            }

            // Update Shift metrics (recalculate cash sales / non-cash sales)
            $shiftService->recalculateShiftMetrics($activeShift->id);

            // Insert Sale Items
            foreach ($itemsToInsert as &$saleItem) {
                $saleItem['sale_id'] = $saleId;
            }
            DB::table('sale_items')->insert($itemsToInsert);

            // Integrate Membership Loyalty & Point Engine
            if ($request->customer_id) {
                $customer = \App\Models\Customer::find($request->customer_id);
                if ($customer) {
                    $pointService = new \App\Services\PointService();
                    $membershipService = new \App\Services\MembershipService();

                    // 1. Redeem Points if requested
                    if ($request->filled('points_to_redeem') && (int) $request->points_to_redeem > 0) {
                        $pointService->redeemPoints($customer, (int) $request->points_to_redeem, 'Sale', $invoiceNumber, "Diskon Poin Transaksi {$invoiceNumber}", $cashierId);
                    }

                    // 2. Earn Points for eligible purchase
                    $pointService->earnPoints($customer, $grandTotal, 'Sale', $invoiceNumber, "Poin Transaksi POS {$invoiceNumber}", $cashierId);

                    // 3. Increment total spending
                    $customer->increment('total_spending', $grandTotal);

                    // 4. Auto Evaluate Membership Tier Upgrade
                    $membershipService->evaluateCustomerTier($customer);
                }
            }

            // Audit Log
            DB::table('audit_logs')->insert([
                'user_id' => $cashierId,
                'action' => 'CREATE_POS_SALE',
                'module' => 'POS',
                'record_id' => $invoiceNumber,
                'new_values' => json_encode(['grand_total' => $grandTotal, 'method' => $request->payment_method]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            if ($idempotencyKey) {
                \Illuminate\Support\Facades\Cache::put('pos_idempotency_' . $idempotencyKey, $invoiceNumber, 60);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaksi POS Berhasil Diproses!',
                'data' => [
                    'sale_id' => $saleId,
                    'invoice_number' => $invoiceNumber,
                    'grand_total' => $grandTotal,
                    'paid_amount' => $request->paid_amount,
                    'change_amount' => $changeAmount,
                ]
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
