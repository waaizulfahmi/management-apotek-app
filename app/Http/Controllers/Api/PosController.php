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
     * Display POS Index Page via Inertia
     */
    public function index()
    {
        $medicines = DB::table('obats')
            ->select('kode', 'nama', 'gambar', 'jenis_obat', 'kategori', 'harga', 'stok')
            ->where('stok', '>', 0)
            ->get();

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

        return Inertia::render('Pos/Index', [
            'medicines' => $medicines,
            'customers' => $customers,
            'prescriptions' => $prescriptions,
            'masterShifts' => $masterShifts,
        ]);
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
            'customer_id' => 'nullable|exists:customers,id',
            'prescription_id' => 'nullable|exists:prescriptions,id',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,debit,credit,qris,transfer,e-wallet,mixed',
            'paid_amount' => 'required|numeric|min:0',
        ]);

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

            $subtotal = 0;
            $itemsToInsert = [];

            foreach ($request->items as $item) {
                // FEFO Stock Deduction
                $allocatedBatches = FefoService::deductStock(
                    $item['kode'],
                    $item['quantity'],
                    $cashierId,
                    $invoiceNumber
                );

                foreach ($allocatedBatches as $allocated) {
                    $itemSubtotal = $allocated['quantity'] * $allocated['sell_price'];
                    $subtotal += $itemSubtotal;

                    $itemsToInsert[] = [
                        'medicine_id' => $item['kode'],
                        'batch_id' => $allocated['batch_id'],
                        'quantity' => $allocated['quantity'],
                        'buy_price' => $allocated['buy_price'],
                        'unit_price' => $allocated['sell_price'],
                        'discount' => 0,
                        'subtotal' => $itemSubtotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            $discount = $request->discount ?? 0;
            $tax = $request->tax ?? 0;
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
                'outlet_id' => $activeShift->outlet_id,
                'customer_id' => $request->customer_id,
                'prescription_id' => $request->prescription_id ?: null,
                'sale_date' => now()->toDateString(),
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
