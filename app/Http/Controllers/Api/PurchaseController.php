<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = DB::table('purchases')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->join('users', 'purchases.user_id', '=', 'users.id')
            ->select('purchases.*', 'suppliers.name as supplier_name', 'users.name as user_name')
            ->orderBy('purchases.id', 'desc')
            ->paginate(10);

        $suppliers = DB::table('suppliers')->where('is_active', true)->get();
        $medicines = DB::table('obats')->get();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'medicines' => $medicines,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:obats,kode',
            'items.*.batch_number' => 'required|string',
            'items.*.expired_date' => 'required|date',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $poNumber = 'PO-' . date('YmdHis') . '-' . rand(100, 999);
            $userId = auth()->id();

            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += ($item['quantity'] * $item['unit_price']);
            }

            $discount = $request->discount ?? 0;
            $tax = $request->tax ?? 0;
            $grandTotal = ($subtotal - $discount) + $tax;

            // Insert Purchase
            $purchaseId = DB::table('purchases')->insertGetId([
                'po_number' => $poNumber,
                'supplier_id' => $request->supplier_id,
                'user_id' => $userId,
                'purchase_date' => $request->purchase_date,
                'due_date' => $request->due_date ?? now()->addDays(30)->toDateString(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'grand_total' => $grandTotal,
                'paid_amount' => $request->paid_amount ?? 0,
                'status' => 'received', // Goods Received
                'payment_status' => ($request->paid_amount >= $grandTotal) ? 'paid' : (($request->paid_amount > 0) ? 'partial' : 'unpaid'),
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $item) {
                // Add Purchase Item
                DB::table('purchase_items')->insert([
                    'purchase_id' => $purchaseId,
                    'medicine_id' => $item['medicine_id'],
                    'batch_number' => $item['batch_number'],
                    'expired_date' => $item['expired_date'],
                    'quantity_ordered' => $item['quantity'],
                    'quantity_received' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Create or Update Medicine Batch (FEFO Batch Entry)
                $batchId = DB::table('medicine_batches')->insertGetId([
                    'medicine_id' => $item['medicine_id'],
                    'batch_number' => $item['batch_number'],
                    'expired_date' => $item['expired_date'],
                    'stock' => $item['quantity'],
                    'buy_price' => $item['unit_price'],
                    'sell_price' => $item['sell_price'] ?? ($item['unit_price'] * 1.2), // Default 20% margin
                    'supplier_id' => $request->supplier_id,
                    'received_date' => $request->purchase_date,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Increment total stock on medicine table
                $medicine = DB::table('obats')->where('kode', $item['medicine_id'])->first();
                DB::table('obats')->where('kode', $item['medicine_id'])->increment('stok', $item['quantity']);

                // Create Stock Movement Log (In)
                DB::table('stock_movements')->insert([
                    'medicine_id' => $item['medicine_id'],
                    'batch_id' => $batchId,
                    'type' => 'in',
                    'quantity' => $item['quantity'],
                    'stock_before' => $medicine->stok,
                    'stock_after' => $medicine->stok + $item['quantity'],
                    'reference_number' => $poNumber,
                    'user_id' => $userId,
                    'notes' => 'Pembelian dari Supplier',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Audit Log
            DB::table('audit_logs')->insert([
                'user_id' => $userId,
                'action' => 'CREATE_PURCHASE',
                'module' => 'Purchases',
                'record_id' => $poNumber,
                'new_values' => json_encode(['grand_total' => $grandTotal, 'supplier_id' => $request->supplier_id]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('purchases.index')->with('success', 'Pembelian berhasil dicatat dan stok batch telah diupdate!');
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
