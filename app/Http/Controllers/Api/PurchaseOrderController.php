<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;
use Carbon\Carbon;

class PurchaseOrderController extends Controller
{
    /**
     * Display PO Dashboard & Table List
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $supplierId = $request->input('supplier_id');

        $query = DB::table('purchase_orders')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->join('users', 'purchase_orders.created_by', '=', 'users.id')
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'suppliers.code as supplier_code',
                'users.name as created_by_name',
                DB::raw('(SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_items.purchase_order_id = purchase_orders.id) as total_items')
            );

        if ($search) {
            $query->where('purchase_orders.po_number', 'like', "%{$search}%");
        }
        if ($status) {
            $query->where('purchase_orders.status', $status);
        }
        if ($supplierId) {
            $query->where('purchase_orders.supplier_id', $supplierId);
        }

        $orders = $query->orderBy('purchase_orders.id', 'desc')->paginate(10)->withQueryString();

        // Dashboard Status Metrics
        $totalMonthCount = DB::table('purchase_orders')->whereMonth('created_at', now()->month)->count();
        $draftCount = DB::table('purchase_orders')->where('status', 'DRAFT')->count();
        $waitingApprovalCount = DB::table('purchase_orders')->where('status', 'WAITING_APPROVAL')->count();
        $approvedCount = DB::table('purchase_orders')->where('status', 'APPROVED')->count();
        $sentCount = DB::table('purchase_orders')->where('status', 'SENT')->count();
        $partialReceivedCount = DB::table('purchase_orders')->where('status', 'PARTIAL_RECEIVED')->count();
        $receivedCount = DB::table('purchase_orders')->where('status', 'RECEIVED')->count();

        $suppliers = DB::table('suppliers')->where('is_active', true)->get();

        return Inertia::render('Purchases/PO/Index', [
            'orders' => $orders,
            'suppliers' => $suppliers,
            'metrics' => [
                'total_month' => $totalMonthCount,
                'draft' => $draftCount,
                'waiting_approval' => $waitingApprovalCount,
                'approved' => $approvedCount,
                'sent' => $sentCount,
                'partial_received' => $partialReceivedCount,
                'received' => $receivedCount,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'supplier_id' => $supplierId,
            ]
        ]);
    }

    /**
     * Smart Reorder Recommendation Engine API
     */
    public function recommendations()
    {
        // Fetch medicines where stock <= min_stok
        $medicines = DB::table('obats')
            ->whereRaw('stok <= min_stok')
            ->get();

        $recommendations = [];

        foreach ($medicines as $med) {
            // Calculate Outstanding PO quantity
            $outstandingPo = DB::table('purchase_order_items')
                ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                ->where('purchase_order_items.medicine_id', $med->kode)
                ->whereIn('purchase_orders.status', ['APPROVED', 'SENT', 'PARTIAL_RECEIVED'])
                ->sum('purchase_order_items.outstanding_quantity');

            $targetStock = $med->max_stok ?? ($med->min_stok * 3);
            $currentStock = $med->stok;
            $recommendedQty = max(0, $targetStock - $currentStock - $outstandingPo);

            if ($recommendedQty > 0) {
                // Fetch last purchase price
                $lastPrice = DB::table('supplier_price_histories')
                    ->where('medicine_id', $med->kode)
                    ->orderBy('id', 'desc')
                    ->value('price') ?? ($med->harga * 0.7);

                $recommendations[] = [
                    'medicine_id' => $med->kode,
                    'medicine_name' => $med->nama,
                    'kategori' => $med->kategori,
                    'current_stock' => $currentStock,
                    'min_stock' => $med->min_stok,
                    'max_stock' => $targetStock,
                    'outstanding_po' => $outstandingPo,
                    'recommended_qty' => $recommendedQty,
                    'unit_price' => $lastPrice,
                ];
            }
        }

        return response()->json(['success' => true, 'data' => $recommendations]);
    }

    /**
     * Create PO Wizard Data
     */
    public function create()
    {
        $suppliers = DB::table('suppliers')->where('is_active', true)->get();
        $medicines = DB::table('obats')->get();

        return Inertia::render('Purchases/PO/Create', [
            'suppliers' => $suppliers,
            'medicines' => $medicines,
        ]);
    }

    /**
     * Store Purchase Order
     */
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_name' => 'required|string',
            'order_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:obats,kode',
            'items.*.order_quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $poNumber = 'PO-PBF-' . date('Ymd') . '-' . str_pad(DB::table('purchase_orders')->whereDate('created_at', now()->toDateString())->count() + 1, 4, '0', STR_PAD_LEFT);
            $userId = auth()->id();

            $subtotal = 0;
            foreach ($request->items as $item) {
                $itemSubtotal = ($item['order_quantity'] * $item['unit_price']) - ($item['discount_amount'] ?? 0);
                $subtotal += $itemSubtotal;
            }

            $discountAmount = $request->discount_amount ?? 0;
            $taxableAmount = $subtotal - $discountAmount;
            $taxAmount = ($taxableAmount * ($request->tax_percent ?? 11)) / 100;
            $grandTotal = $taxableAmount + $taxAmount + ($request->shipping_cost ?? 0);

            // Approval threshold check (< 5 Juta Auto-Approved)
            $status = $grandTotal >= 5000000 ? 'WAITING_APPROVAL' : 'APPROVED';

            $poId = DB::table('purchase_orders')->insertGetId([
                'po_number' => $poNumber,
                'supplier_id' => $request->supplier_id,
                'warehouse_name' => $request->warehouse_name,
                'order_date' => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'payment_term_days' => $request->payment_term_days ?? 30,
                'due_date' => Carbon::parse($request->order_date)->addDays($request->payment_term_days ?? 30)->toDateString(),
                'status' => $status,
                'subtotal' => $subtotal,
                'discount_percent' => $request->discount_percent ?? 0,
                'discount_amount' => $discountAmount,
                'tax_percent' => $request->tax_percent ?? 11,
                'tax_amount' => $taxAmount,
                'shipping_cost' => $request->shipping_cost ?? 0,
                'grand_total' => $grandTotal,
                'notes_supplier' => $request->notes_supplier,
                'notes_internal' => $request->notes_internal,
                'created_by' => $userId,
                'approved_by' => $status === 'APPROVED' ? $userId : null,
                'approved_at' => $status === 'APPROVED' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $item) {
                $itemSubtotal = ($item['order_quantity'] * $item['unit_price']) - ($item['discount_amount'] ?? 0);

                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $poId,
                    'medicine_id' => $item['medicine_id'],
                    'order_quantity' => $item['order_quantity'],
                    'bonus_quantity' => $item['bonus_quantity'] ?? 0,
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'subtotal' => $itemSubtotal,
                    'received_quantity' => 0,
                    'outstanding_quantity' => $item['order_quantity'],
                    'notes' => $item['notes'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Track supplier price history
                DB::table('supplier_price_histories')->insert([
                    'supplier_id' => $request->supplier_id,
                    'medicine_id' => $item['medicine_id'],
                    'price' => $item['unit_price'],
                    'transaction_date' => $request->order_date,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Audit Log
            DB::table('audit_logs')->insert([
                'user_id' => $userId,
                'action' => 'CREATE_PURCHASE_ORDER',
                'module' => 'PurchaseOrder',
                'record_id' => $poNumber,
                'new_values' => json_encode(['grand_total' => $grandTotal, 'status' => $status]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('purchases.po.index')->with('success', "Purchase Order {$poNumber} berhasil dibuat dengan status {$status}!");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Goods Receipt Form
     */
    public function receiveForm($id)
    {
        $po = DB::table('purchase_orders')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name', 'suppliers.code as supplier_code')
            ->where('purchase_orders.id', $id)
            ->first();

        if (!$po) abort(404);

        $items = DB::table('purchase_order_items')
            ->join('obats', 'purchase_order_items.medicine_id', '=', 'obats.kode')
            ->select('purchase_order_items.*', 'obats.nama as medicine_name')
            ->where('purchase_order_items.purchase_order_id', $id)
            ->get();

        return Inertia::render('Purchases/PO/Receive', [
            'po' => $po,
            'items' => $items,
        ]);
    }

    /**
     * Goods Receipt Store (Atomic Inventory & Accounts Payable Posting)
     */
    public function receiveStore(Request $request, $id)
    {
        $request->validate([
            'supplier_invoice_number' => 'required|string',
            'received_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:purchase_order_items,id',
            'items.*.batch_number' => 'required|string',
            'items.*.expired_date' => 'required|date',
            'items.*.received_quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $po = DB::table('purchase_orders')->where('id', $id)->first();
            $userId = auth()->id();

            $receiptNo = 'GR-' . date('Ymd') . '-' . rand(100, 999);
            $receiptId = DB::table('purchase_receipts')->insertGetId([
                'receipt_number' => $receiptNo,
                'purchase_order_id' => $id,
                'supplier_id' => $po->supplier_id,
                'supplier_invoice_number' => $request->supplier_invoice_number,
                'received_date' => $request->received_date,
                'received_by' => $userId,
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totalReceiptCost = 0;

            foreach ($request->items as $itemData) {
                $poItem = DB::table('purchase_order_items')->where('id', $itemData['item_id'])->first();

                // 1. Insert Receipt Item Detail
                DB::table('purchase_receipt_items')->insert([
                    'purchase_receipt_id' => $receiptId,
                    'medicine_id' => $poItem->medicine_id,
                    'batch_number' => $itemData['batch_number'],
                    'expired_date' => $itemData['expired_date'],
                    'received_quantity' => $itemData['received_quantity'],
                    'unit_cost' => $poItem->unit_price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 2. Insert Batch in FEFO inventory
                DB::table('medicine_batches')->insert([
                    'medicine_id' => $poItem->medicine_id,
                    'batch_number' => $itemData['batch_number'],
                    'expired_date' => $itemData['expired_date'],
                    'stock' => $itemData['received_quantity'],
                    'buy_price' => $poItem->unit_price,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 3. Increment Main Medicine Stock
                $medBefore = DB::table('obats')->where('kode', $poItem->medicine_id)->value('stok') ?? 0;
                DB::table('obats')->where('kode', $poItem->medicine_id)->increment('stok', $itemData['received_quantity']);

                // 4. Create Stock Movement Log
                DB::table('stock_movements')->insert([
                    'medicine_id' => $poItem->medicine_id,
                    'batch_id' => null,
                    'type' => 'in',
                    'quantity' => $itemData['received_quantity'],
                    'stock_before' => $medBefore,
                    'stock_after' => $medBefore + $itemData['received_quantity'],
                    'reference_number' => $po->po_number,
                    'user_id' => $userId,
                    'notes' => "Penerimaan Barang PO (Inv: {$request->supplier_invoice_number}, Batch: {$itemData['batch_number']})",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 5. Update PO Item Received & Outstanding Qty
                $newReceived = $poItem->received_quantity + $itemData['received_quantity'];
                $newOutstanding = max(0, $poItem->order_quantity - $newReceived);

                DB::table('purchase_order_items')->where('id', $itemData['item_id'])->update([
                    'received_quantity' => $newReceived,
                    'outstanding_quantity' => $newOutstanding,
                    'updated_at' => now(),
                ]);

                $totalReceiptCost += ($itemData['received_quantity'] * $poItem->unit_price);
            }

            // Check if all items received
            $totalOutstanding = DB::table('purchase_order_items')->where('purchase_order_id', $id)->sum('outstanding_quantity');
            $newPoStatus = $totalOutstanding <= 0 ? 'RECEIVED' : 'PARTIAL_RECEIVED';

            DB::table('purchase_orders')->where('id', $id)->update([
                'status' => $newPoStatus,
                'updated_at' => now(),
            ]);

            // 6. Create Accounts Payable (Hutang Supplier) in Finance Module
            $apNo = 'AP-' . date('Ymd') . '-' . rand(100, 999);
            DB::table('accounts_payables')->insert([
                'payable_number' => $apNo,
                'purchase_id' => null,
                'supplier_id' => $po->supplier_id,
                'invoice_number' => $request->supplier_invoice_number,
                'total_amount' => $totalReceiptCost,
                'paid_amount' => 0,
                'remaining_amount' => $totalReceiptCost,
                'due_date' => $po->due_date ?? now()->addDays(30)->toDateString(),
                'status' => 'unpaid',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('purchases.po.index')->with('success', "Penerimaan Barang berhasil! Stok obat & Kewajiban Hutang Supplier telah diperbarui.");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display PO Detail Page
     */
    public function show($id)
    {
        $po = DB::table('purchase_orders')
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->join('users as creator', 'purchase_orders.created_by', '=', 'creator.id')
            ->leftJoin('users as approver', 'purchase_orders.approved_by', '=', 'approver.id')
            ->select(
                'purchase_orders.*',
                'suppliers.name as supplier_name',
                'suppliers.code as supplier_code',
                'suppliers.phone as supplier_phone',
                'suppliers.email as supplier_email',
                'suppliers.address as supplier_address',
                'creator.name as created_by_name',
                'approver.name as approved_by_name'
            )
            ->where('purchase_orders.id', $id)
            ->first();

        if (!$po) abort(404);

        $items = DB::table('purchase_order_items')
            ->join('obats', 'purchase_order_items.medicine_id', '=', 'obats.kode')
            ->select(
                'purchase_order_items.*',
                'obats.nama as medicine_name',
                'obats.jenis_obat as medicine_unit',
                'obats.kategori as medicine_category'
            )
            ->where('purchase_order_items.purchase_order_id', $id)
            ->get();

        $receipts = DB::table('purchase_receipts')
            ->join('users', 'purchase_receipts.received_by', '=', 'users.id')
            ->select('purchase_receipts.*', 'users.name as received_by_name')
            ->where('purchase_receipts.purchase_order_id', $id)
            ->get();

        foreach ($receipts as $r) {
            $r->items = DB::table('purchase_receipt_items')
                ->join('obats', 'purchase_receipt_items.medicine_id', '=', 'obats.kode')
                ->select('purchase_receipt_items.*', 'obats.nama as medicine_name')
                ->where('purchase_receipt_items.purchase_receipt_id', $r->id)
                ->get();
        }

        return Inertia::render('Purchases/PO/Show', [
            'po' => $po,
            'items' => $items,
            'receipts' => $receipts,
        ]);
    }

    /**
     * Approve Purchase Order
     */
    public function approve($id)
    {
        DB::table('purchase_orders')->where('id', $id)->update([
            'status' => 'APPROVED',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Purchase Order berhasil disetujui!');
    }

    /**
     * Cancel Purchase Order
     */
    public function cancel($id)
    {
        DB::table('purchase_orders')->where('id', $id)->update([
            'status' => 'CANCELLED',
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Purchase Order telah dibatalkan.');
    }
}

