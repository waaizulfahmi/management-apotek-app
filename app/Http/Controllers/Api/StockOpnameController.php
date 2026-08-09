<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class StockOpnameController extends Controller
{
    /**
     * Display Stock Opname Dashboard & Table List
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $warehouse = $request->input('warehouse');

        $query = DB::table('stock_opnames')
            ->join('users', 'stock_opnames.user_id', '=', 'users.id')
            ->select(
                'stock_opnames.*',
                'users.name as user_name',
                DB::raw('(SELECT COUNT(*) FROM stock_opname_items WHERE stock_opname_items.stock_opname_id = stock_opnames.id) as total_items'),
                DB::raw('(SELECT SUM(difference) FROM stock_opname_items WHERE stock_opname_items.stock_opname_id = stock_opnames.id) as total_difference')
            );

        if ($search) {
            $query->where('stock_opnames.opname_number', 'like', "%{$search}%");
        }
        if ($status) {
            $query->where('stock_opnames.status', $status);
        }
        if ($warehouse) {
            $query->where('stock_opnames.warehouse_name', $warehouse);
        }

        $opnames = $query->orderBy('stock_opnames.id', 'desc')->paginate(10)->withQueryString();

        // Mini Dashboard Metrics
        $activeSoCount = DB::table('stock_opnames')->whereIn('status', ['Counting', 'Review'])->count();
        $waitingApprovalCount = DB::table('stock_opnames')->where('status', 'Waiting Approval')->count();
        $completedMonthCount = DB::table('stock_opnames')->where('status', 'Completed')->whereMonth('created_at', now()->month)->count();
        $totalDifferenceCount = DB::table('stock_opname_items')->where('difference', '!=', 0)->count();

        $categories = DB::table('medicine_categories')->get();
        $suppliers = DB::table('suppliers')->get();

        return Inertia::render('Inventory/StockOpname/Index', [
            'opnames' => $opnames,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'metrics' => [
                'active' => $activeSoCount,
                'waiting_approval' => $waitingApprovalCount,
                'completed_month' => $completedMonthCount,
                'total_differences' => $totalDifferenceCount,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'warehouse' => $warehouse,
            ]
        ]);
    }

    /**
     * Store Wizard (SO Creation & Stock Snapshot)
     */
    public function store(Request $request)
    {
        $request->validate([
            'warehouse_name' => 'required|string',
            'opname_date' => 'required|date',
            'scope_type' => 'required|in:all,category,supplier,stock_status,manual',
            'lock_stock' => 'boolean',
            'threshold_difference' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $soNumber = 'SO-' . date('Ymd') . '-' . str_pad(DB::table('stock_opnames')->whereDate('created_at', now()->toDateString())->count() + 1, 4, '0', STR_PAD_LEFT);
            $userId = auth()->id();

            $opnameId = DB::table('stock_opnames')->insertGetId([
                'opname_number' => $soNumber,
                'warehouse_name' => $request->warehouse_name,
                'scope_type' => $request->scope_type,
                'scope_filter' => $request->scope_filter,
                'lock_stock' => $request->lock_stock ?? false,
                'threshold_difference' => $request->threshold_difference,
                'opname_date' => $request->opname_date,
                'user_id' => $userId,
                'status' => 'Counting',
                'counting_started_at' => now(),
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Scope Query to fetch medicines & batches snapshot
            $medQuery = DB::table('obats');

            if ($request->scope_type === 'category' && $request->scope_filter) {
                $medQuery->where('kategori', $request->scope_filter);
            } elseif ($request->scope_type === 'stock_status') {
                if ($request->scope_filter === 'low') $medQuery->where('stok', '<=', 10);
                elseif ($request->scope_filter === 'zero') $medQuery->where('stok', '=', 0);
                elseif ($request->scope_filter === 'positive') $medQuery->where('stok', '>', 0);
            }

            $medicines = $medQuery->get();

            foreach ($medicines as $med) {
                // Fetch batches for exact batch snapshot
                $batches = DB::table('medicine_batches')
                    ->where('medicine_id', $med->kode)
                    ->where('is_active', true)
                    ->get();

                if ($batches->count() > 0) {
                    foreach ($batches as $b) {
                        DB::table('stock_opname_items')->insert([
                            'stock_opname_id' => $opnameId,
                            'medicine_id' => $med->kode,
                            'batch_id' => $b->id,
                            'system_stock' => $b->stock,
                            'physical_stock' => $b->stock, // default initial physical = system
                            'difference' => 0,
                            'match_status' => 'MATCH',
                            'count_status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } else {
                    DB::table('stock_opname_items')->insert([
                        'stock_opname_id' => $opnameId,
                        'medicine_id' => $med->kode,
                        'batch_id' => null,
                        'system_stock' => $med->stok,
                        'physical_stock' => $med->stok,
                        'difference' => 0,
                        'match_status' => 'MATCH',
                        'count_status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Audit Log
            DB::table('audit_logs')->insert([
                'user_id' => $userId,
                'action' => 'CREATE_STOCK_OPNAME',
                'module' => 'StockOpname',
                'record_id' => $soNumber,
                'new_values' => json_encode(['warehouse' => $request->warehouse_name, 'scope' => $request->scope_type]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('inventory.opname.counting', $opnameId)->with('success', "Stok Opname {$soNumber} berhasil dibuat!");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Fast Counting Mode View
     */
    public function countingMode($id)
    {
        $opname = DB::table('stock_opnames')->where('id', $id)->first();
        if (!$opname) abort(404);

        $items = DB::table('stock_opname_items')
            ->join('obats', 'stock_opname_items.medicine_id', '=', 'obats.kode')
            ->leftJoin('medicine_batches', 'stock_opname_items.batch_id', '=', 'medicine_batches.id')
            ->select('stock_opname_items.*', 'obats.nama as medicine_name', 'obats.kode as barcode', 'medicine_batches.batch_number', 'medicine_batches.expired_date')
            ->where('stock_opname_items.stock_opname_id', $id)
            ->orderBy('stock_opname_items.id', 'asc')
            ->get();

        $totalCounted = $items->where('count_status', 'counted')->count();
        $progress = $items->count() > 0 ? round(($totalCounted / $items->count()) * 100) : 0;

        return Inertia::render('Inventory/StockOpname/Counting', [
            'opname' => $opname,
            'items' => $items,
            'progress' => $progress,
            'counted_count' => $totalCounted,
            'total_items' => $items->count(),
        ]);
    }

    /**
     * Fast Barcode Scan API Endpoint
     */
    public function scanBarcode($id, $barcode)
    {
        $item = DB::table('stock_opname_items')
            ->join('obats', 'stock_opname_items.medicine_id', '=', 'obats.kode')
            ->leftJoin('medicine_batches', 'stock_opname_items.batch_id', '=', 'medicine_batches.id')
            ->select('stock_opname_items.*', 'obats.nama as medicine_name', 'obats.kode as barcode', 'medicine_batches.batch_number', 'medicine_batches.expired_date')
            ->where('stock_opname_items.stock_opname_id', $id)
            ->where(function($query) use ($barcode) {
                $query->where('obats.kode', $barcode)
                      ->orWhere('medicine_batches.batch_number', $barcode);
            })
            ->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Barcode / Obat tidak ditemukan pada daftar SO ini'], 444);
        }

        return response()->json(['success' => true, 'data' => $item]);
    }

    /**
     * Count Item (Save Physical Stock & Calculate Difference)
     */
    public function countItem(Request $request, $id)
    {
        $request->validate([
            'item_id' => 'required|exists:stock_opname_items,id',
            'physical_stock' => 'required|integer|min:0',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $item = DB::table('stock_opname_items')->where('id', $request->item_id)->first();
        $opname = DB::table('stock_opnames')->where('id', $id)->first();

        $physical = $request->physical_stock;
        $system = $item->system_stock;
        $diff = $physical - $system;

        $matchStatus = 'MATCH';
        if ($diff < 0) $matchStatus = 'SHORTAGE';
        elseif ($diff > 0) $matchStatus = 'SURPLUS';

        $requiresRecount = abs($diff) > $opname->threshold_difference;

        DB::table('stock_opname_items')->where('id', $request->item_id)->update([
            'physical_stock' => $physical,
            'difference' => $diff,
            'match_status' => $matchStatus,
            'requires_recount' => $requiresRecount,
            'count_status' => 'counted',
            'reason' => $request->reason,
            'notes' => $request->notes,
            'updated_at' => now(),
        ]);

        // Insert Count Audit History
        $countNumber = DB::table('stock_opname_counts')->where('stock_opname_item_id', $request->item_id)->count() + 1;
        DB::table('stock_opname_counts')->insert([
            'stock_opname_item_id' => $request->item_id,
            'count_number' => $countNumber,
            'quantity' => $physical,
            'counted_by' => auth()->id(),
            'notes' => "Count #{$countNumber} (Physical: {$physical}, Diff: {$diff})",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stok fisik berhasil disimpan!',
            'data' => [
                'physical_stock' => $physical,
                'difference' => $diff,
                'match_status' => $matchStatus,
                'requires_recount' => $requiresRecount,
            ]
        ]);
    }

    /**
     * Submit for Review / Approval
     */
    public function submitApproval($id)
    {
        DB::table('stock_opnames')->where('id', $id)->update([
            'status' => 'Waiting Approval',
            'counting_completed_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('inventory.opname.review', $id)->with('success', 'Stok Opname telah diajukan untuk Approval!');
    }

    /**
     * Review Differences View
     */
    public function reviewMode($id)
    {
        $opname = DB::table('stock_opnames')->where('id', $id)->first();
        if (!$opname) abort(404);

        $items = DB::table('stock_opname_items')
            ->join('obats', 'stock_opname_items.medicine_id', '=', 'obats.kode')
            ->leftJoin('medicine_batches', 'stock_opname_items.batch_id', '=', 'medicine_batches.id')
            ->select('stock_opname_items.*', 'obats.nama as medicine_name', 'obats.harga as unit_price', 'medicine_batches.batch_number')
            ->where('stock_opname_items.stock_opname_id', $id)
            ->get();

        $totalItems = $items->count();
        $matchCount = $items->where('match_status', 'MATCH')->count();
        $shortageCount = $items->where('match_status', 'SHORTAGE')->count();
        $surplusCount = $items->where('match_status', 'SURPLUS')->count();

        $totalShortageValue = $items->where('match_status', 'SHORTAGE')->sum(fn($i) => abs($i->difference) * $i->unit_price);
        $totalSurplusValue = $items->where('match_status', 'SURPLUS')->sum(fn($i) => abs($i->difference) * $i->unit_price);

        return Inertia::render('Inventory/StockOpname/Review', [
            'opname' => $opname,
            'items' => $items,
            'summary' => [
                'total' => $totalItems,
                'match' => $matchCount,
                'shortage' => $shortageCount,
                'surplus' => $surplusCount,
                'shortage_val' => $totalShortageValue,
                'surplus_val' => $totalSurplusValue,
            ]
        ]);
    }

    /**
     * Approve & Finalize Stock Opname (Automatic Stock Adjustment)
     */
    public function approve(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $opname = DB::table('stock_opnames')->where('id', $id)->first();
            $userId = auth()->id();

            $items = DB::table('stock_opname_items')->where('stock_opname_id', $id)->get();

            foreach ($items as $item) {
                if ($item->difference != 0) {
                    // Update batch stock if batch_id exists
                    if ($item->batch_id) {
                        DB::table('medicine_batches')->where('id', $item->batch_id)->update([
                            'stock' => $item->physical_stock,
                            'updated_at' => now(),
                        ]);
                    }

                    // Update main medicine stock
                    DB::table('obats')->where('kode', $item->medicine_id)->update([
                        'stok' => $item->physical_stock,
                    ]);

                    // Insert Stock Movement Adjustment Log
                    DB::table('stock_movements')->insert([
                        'medicine_id' => $item->medicine_id,
                        'batch_id' => $item->batch_id,
                        'type' => 'adjustment',
                        'quantity' => abs($item->difference),
                        'stock_before' => $item->system_stock,
                        'stock_after' => $item->physical_stock,
                        'reference_number' => $opname->opname_number,
                        'user_id' => $userId,
                        'notes' => "SO Finalized (Selisih: {$item->difference}, Alasan: {$item->reason})",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Update Stock Opname Status to Completed
            DB::table('stock_opnames')->where('id', $id)->update([
                'status' => 'Completed',
                'approved_by' => $userId,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

            // Audit Log
            DB::table('audit_logs')->insert([
                'user_id' => $userId,
                'action' => 'FINALIZE_STOCK_OPNAME',
                'module' => 'StockOpname',
                'record_id' => $opname->opname_number,
                'new_values' => json_encode(['status' => 'Completed']),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return redirect()->route('inventory.opname')->with('success', "Stok Opname {$opname->opname_number} berhasil disetujui & stok fisik telah di-adjust!");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
