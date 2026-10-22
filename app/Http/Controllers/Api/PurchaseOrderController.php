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
        $suppliers = DB::table('suppliers')->where('is_active', true)->orderBy('name')->get();
        $medicines = DB::table('obats')
            ->select('kode', 'nama', 'jenis_obat', 'kategori', 'stok', 'satuan_pembelian_id')
            ->orderBy('nama')
            ->get();

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
            'items.*.unit_id' => 'nullable|exists:units,id',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        $conversionService = app(\App\Services\UnitConversionService::class);

        DB::beginTransaction();
        try {
            $poNumber = 'PO-PBF-' . date('Ymd') . '-' . str_pad(DB::table('purchase_orders')->whereDate('created_at', now()->toDateString())->count() + 1, 4, '0', STR_PAD_LEFT);
            $userId = auth()->id();

            // Sesuai aturan pengadaan apotek: PO hanya untuk pemesanan kuantitas (SP),
            // harga tidak perlu diinput karena dapat berubah sewaktu-waktu dan dicatat resmi di Faktur.
            $status = 'APPROVED';

            $poId = DB::table('purchase_orders')->insertGetId([
                'po_number' => $poNumber,
                'supplier_id' => $request->supplier_id,
                'warehouse_name' => $request->warehouse_name,
                'order_date' => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'payment_term_days' => $request->payment_term_days ?? 30,
                'due_date' => Carbon::parse($request->order_date)->addDays($request->payment_term_days ?? 30)->toDateString(),
                'status' => $status,
                'subtotal' => 0,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'tax_percent' => 0,
                'tax_amount' => 0,
                'shipping_cost' => 0,
                'grand_total' => 0,
                'notes_supplier' => $request->notes_supplier,
                'notes_internal' => $request->notes_internal,
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $item) {
                $medKode = $item['medicine_id'];
                $unitId = isset($item['unit_id']) ? (int)$item['unit_id'] : null;
                $unitName = null;
                $conversionFactor = 1.0000;

                if ($unitId) {
                    $uObj = \App\Models\Unit::find($unitId);
                    if ($uObj) $unitName = $uObj->name;
                    $conversionFactor = $conversionService->getConversionFactor($medKode, $unitId);
                }

                $qtyBase = (int)ceil($item['order_quantity'] * $conversionFactor);

                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $poId,
                    'medicine_id' => $medKode,
                    'unit_id' => $unitId,
                    'unit_name' => $unitName,
                    'conversion_to_base' => $conversionFactor,
                    'quantity_base' => $qtyBase,
                    'order_quantity' => $item['order_quantity'],
                    'bonus_quantity' => $item['bonus_quantity'] ?? 0,
                    'unit_price' => 0,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'subtotal' => 0,
                    'received_quantity' => 0,
                    'outstanding_quantity' => $item['order_quantity'],
                    'notes' => $item['notes'] ?? null,
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
                'new_values' => json_encode(['total_items' => count($request->items), 'status' => $status]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('purchases.po.index')->with('success', "Surat Pesanan {$poNumber} berhasil dibuat!");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Goods Receipt Form - Dialihkan ke Faktur Pembelian Farmasi (Single Source of Truth untuk Stok)
     */
    public function receiveForm($id)
    {
        return redirect()->route('purchases.invoices.create', ['po_id' => $id])
            ->with('info', 'Pendataan penerimaan barang fisik dan penambahan stok obat dilakukan melalui Faktur Pembelian.');
    }

    /**
     * Goods Receipt Store - Dialihkan ke Faktur Pembelian Farmasi
     */
    public function receiveStore(Request $request, $id)
    {
        return redirect()->route('purchases.invoices.create', ['po_id' => $id])
            ->with('info', 'Penerimaan barang fisik dan penambahan stok persediaan obat wajib diproses melalui modul Faktur Pembelian.');
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

        $invoices = DB::table('purchases')
            ->leftJoin('users', 'purchases.user_id', '=', 'users.id')
            ->select('purchases.*', 'users.name as creator_name')
            ->where('purchases.purchase_order_id', $id)
            ->orderBy('purchases.id', 'desc')
            ->get();

        return Inertia::render('Purchases/PO/Show', [
            'po' => $po,
            'items' => $items,
            'receipts' => $receipts,
            'invoices' => $invoices,
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

