<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use Exception;

class StockMovementController extends Controller
{
    /**
     * Display Kartu Stok & Advanced Traceability Engine
     */
    public function index(Request $request)
    {
        $datePreset = $request->input('date_preset', 'this_month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $medicineId = $request->input('medicine_id');
        $kategori = $request->input('kategori');
        $warehouseName = $request->input('warehouse_name');
        $batchNumber = $request->input('batch_number');
        $expiredStatus = $request->input('expired_status');
        $supplierId = $request->input('supplier_id');
        $movementType = $request->input('movement_type'); // Enum or array
        $userId = $request->input('user_id');
        $search = $request->input('search');
        $quickFilter = $request->input('quick_filter');

        // 1. Resolve Date Range
        if ($datePreset === 'today') {
            $startDate = Carbon::today()->toDateString();
            $endDate = Carbon::today()->toDateString();
        } elseif ($datePreset === 'yesterday') {
            $startDate = Carbon::yesterday()->toDateString();
            $endDate = Carbon::yesterday()->toDateString();
        } elseif ($datePreset === '7days') {
            $startDate = Carbon::now()->subDays(6)->toDateString();
            $endDate = Carbon::now()->toDateString();
        } elseif ($datePreset === '30days') {
            $startDate = Carbon::now()->subDays(29)->toDateString();
            $endDate = Carbon::now()->toDateString();
        } elseif ($datePreset === 'last_month') {
            $startDate = Carbon::now()->subMonth()->startOfMonth()->toDateString();
            $endDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();
        } elseif ($datePreset === 'this_year') {
            $startDate = Carbon::now()->startOfYear()->toDateString();
            $endDate = Carbon::now()->endOfYear()->toDateString();
        } elseif ($datePreset === 'custom' && $startDate && $endDate) {
            // keep custom dates
        } else {
            // default: this_month
            $startDate = Carbon::now()->startOfMonth()->toDateString();
            $endDate = Carbon::now()->endOfMonth()->toDateString();
        }

        // 2. Calculate Opening Stock (Sum of IN minus OUT before startDate)
        $openingQuery = DB::table('stock_movements')
            ->where('status', 'POSTED')
            ->whereDate('created_at', '<', $startDate);

        if ($medicineId) {
            $openingQuery->where('medicine_id', $medicineId);
        }
        if ($warehouseName) {
            $openingQuery->where('warehouse_name', $warehouseName);
        }

        $openingIn = (clone $openingQuery)->where('type', 'in')->sum('quantity');
        $openingOut = (clone $openingQuery)->where('type', 'out')->sum('quantity');
        $openingStock = $openingIn - $openingOut;

        // 3. Build Main Movements Query
        $query = DB::table('stock_movements')
            ->join('obats', 'stock_movements.medicine_id', '=', 'obats.kode')
            ->join('users', 'stock_movements.user_id', '=', 'users.id')
            ->leftJoin('suppliers', 'stock_movements.supplier_id', '=', 'suppliers.id')
            ->select(
                'stock_movements.*',
                'obats.nama as medicine_name',
                'obats.kategori as medicine_category',
                'users.name as user_name',
                'suppliers.name as supplier_name'
            )
            ->whereBetween(DB::raw('DATE(stock_movements.created_at)'), [$startDate, $endDate]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('obats.nama', 'like', "%{$search}%")
                  ->orWhere('stock_movements.medicine_id', 'like', "%{$search}%")
                  ->orWhere('stock_movements.movement_number', 'like', "%{$search}%")
                  ->orWhere('stock_movements.reference_number', 'like', "%{$search}%")
                  ->orWhere('stock_movements.batch_number', 'like', "%{$search}%");
            });
        }

        if ($medicineId) {
            $query->where('stock_movements.medicine_id', $medicineId);
        }
        if ($kategori) {
            $query->where('obats.kategori', $kategori);
        }
        if ($warehouseName) {
            $query->where('stock_movements.warehouse_name', $warehouseName);
        }
        if ($batchNumber) {
            $query->where('stock_movements.batch_number', $batchNumber);
        }
        if ($supplierId) {
            $query->where('stock_movements.supplier_id', $supplierId);
        }
        if ($userId) {
            $query->where('stock_movements.user_id', $userId);
        }
        if ($movementType) {
            if (is_array($movementType)) {
                $query->whereIn('stock_movements.movement_type', $movementType);
            } else {
                $query->where('stock_movements.movement_type', $movementType);
            }
        }

        // Quick Filters
        if ($quickFilter === 'in') {
            $query->where('stock_movements.type', 'in');
        } elseif ($quickFilter === 'out') {
            $query->where('stock_movements.type', 'out');
        } elseif ($quickFilter === 'return') {
            $query->whereIn('stock_movements.movement_type', ['PURCHASE_RETURN', 'SALE_RETURN']);
        } elseif ($quickFilter === 'opname') {
            $query->where('stock_movements.movement_type', 'STOCK_OPNAME');
        } elseif ($quickFilter === 'damaged_expired') {
            $query->whereIn('stock_movements.movement_type', ['DAMAGED', 'EXPIRED', 'LOST']);
        }

        // Expired Status Filter
        if ($expiredStatus === 'expired_30') {
            $query->whereBetween('stock_movements.expired_date', [now()->toDateString(), now()->addDays(30)->toDateString()]);
        } elseif ($expiredStatus === 'expired_past') {
            $query->where('stock_movements.expired_date', '<', now()->toDateString());
        }

        $movements = $query->orderBy('stock_movements.id', 'desc')->paginate(25)->withQueryString();

        // 4. Calculate Summary Metrics for Period
        $periodIn = (clone $query)->where('stock_movements.type', 'in')->sum('stock_movements.quantity');
        $periodOut = (clone $query)->where('stock_movements.type', 'out')->sum('stock_movements.quantity');
        $closingStock = $openingStock + $periodIn - $periodOut;

        // Fetch Dropdown Master Data
        $medicines = DB::table('obats')->select('kode', 'nama', 'kategori', 'stok')->get();
        $categories = DB::table('obats')->distinct()->pluck('kategori');
        $suppliers = DB::table('suppliers')->where('is_active', true)->get();
        $users = DB::table('users')->select('id', 'name', 'role')->get();

        // Selected Medicine Quick View Detail if Single Medicine Selected
        $selectedMedicineDetail = null;
        if ($medicineId) {
            $med = DB::table('obats')->where('kode', $medicineId)->first();
            if ($med) {
                $batches = DB::table('medicine_batches')
                    ->where('medicine_id', $medicineId)
                    ->where('stock', '>', 0)
                    ->get();

                $selectedMedicineDetail = [
                    'kode' => $med->kode,
                    'nama' => $med->nama,
                    'kategori' => $med->kategori,
                    'stok' => $med->stok,
                    'min_stok' => $med->min_stok,
                    'max_stok' => $med->max_stok ?? ($med->min_stok * 3),
                    'batches' => $batches,
                ];
            }
        }

        return Inertia::render('Inventory/Movements', [
            'movements' => $movements,
            'summary' => [
                'opening_stock' => $openingStock,
                'period_in' => $periodIn,
                'period_out' => $periodOut,
                'closing_stock' => $closingStock,
            ],
            'medicines' => $medicines,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'users' => $users,
            'selectedMedicineDetail' => $selectedMedicineDetail,
            'filters' => [
                'date_preset' => $datePreset,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'medicine_id' => $medicineId,
                'kategori' => $kategori,
                'warehouse_name' => $warehouseName,
                'batch_number' => $batchNumber,
                'expired_status' => $expiredStatus,
                'supplier_id' => $supplierId,
                'movement_type' => $movementType,
                'user_id' => $userId,
                'search' => $search,
                'quick_filter' => $quickFilter,
            ]
        ]);
    }

    /**
     * Reconcile Stock Master vs Movement Calculations
     */
    public function reconcile(Request $request)
    {
        $medicineId = $request->input('medicine_id');

        $query = DB::table('obats');
        if ($medicineId) {
            $query->where('kode', $medicineId);
        }

        $medicines = $query->get();
        $results = [];

        foreach ($medicines as $med) {
            $sumIn = DB::table('stock_movements')
                ->where('medicine_id', $med->kode)
                ->where('status', 'POSTED')
                ->where('type', 'in')
                ->sum('quantity');

            $sumOut = DB::table('stock_movements')
                ->where('medicine_id', $med->kode)
                ->where('status', 'POSTED')
                ->where('type', 'out')
                ->sum('quantity');

            $calculated = $sumIn - $sumOut;
            $masterStock = $med->stok;
            $diff = $masterStock - $calculated;
            $matchStatus = $diff === 0 ? 'MATCH' : 'MISMATCH';

            // Log Reconciliation Record
            $recNo = 'REC-' . date('Ymd') . '-' . rand(100, 999);
            DB::table('stock_reconciliations')->insert([
                'reconcile_number' => $recNo,
                'medicine_id' => $med->kode,
                'master_stock' => $masterStock,
                'calculated_stock' => $calculated,
                'difference' => $diff,
                'status' => $matchStatus,
                'checked_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $results[] = [
                'medicine_id' => $med->kode,
                'medicine_name' => $med->nama,
                'master_stock' => $masterStock,
                'calculated_stock' => $calculated,
                'difference' => $diff,
                'status' => $matchStatus,
            ];
        }

        return response()->json(['success' => true, 'data' => $results]);
    }

    /**
     * Store Manual Stock Adjustment (Barang Rusak, Expired, Hilang, Bonus)
     */
    public function storeAdjustment(Request $request)
    {
        $request->validate([
            'medicine_id' => 'required|exists:obats,kode',
            'movement_type' => 'required|in:ADJUSTMENT_IN,ADJUSTMENT_OUT,DAMAGED,EXPIRED,LOST,BONUS',
            'quantity' => 'required|integer|min:1',
            'warehouse_name' => 'required|string',
            'notes' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $med = DB::table('obats')->where('kode', $request->medicine_id)->first();
            $userId = auth()->id();

            $type = in_array($request->movement_type, ['ADJUSTMENT_IN', 'BONUS']) ? 'in' : 'out';
            $stockBefore = $med->stok;
            $stockAfter = $type === 'in' ? ($stockBefore + $request->quantity) : max(0, $stockBefore - $request->quantity);

            $smNo = 'SM-' . date('Ymd') . '-' . str_pad(DB::table('stock_movements')->whereDate('created_at', now()->toDateString())->count() + 1, 5, '0', STR_PAD_LEFT);

            // 1. Insert Movement Log
            DB::table('stock_movements')->insert([
                'movement_number' => $smNo,
                'medicine_id' => $request->medicine_id,
                'batch_number' => $request->batch_number,
                'expired_date' => $request->expired_date,
                'supplier_id' => $request->supplier_id,
                'warehouse_name' => $request->warehouse_name,
                'type' => $type,
                'movement_type' => $request->movement_type,
                'quantity' => $request->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'unit_cost' => $med->harga * 0.7,
                'reference_number' => $smNo,
                'notes' => $request->notes,
                'user_id' => $userId,
                'status' => 'POSTED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Adjust Main Medicine Stock
            if ($type === 'in') {
                DB::table('obats')->where('kode', $request->medicine_id)->increment('stok', $request->quantity);
            } else {
                DB::table('obats')->where('kode', $request->medicine_id)->decrement('stok', $request->quantity);
            }

            DB::commit();

            return redirect()->back()->with('success', "Penyesuaian Stok {$smNo} berhasil disimpan!");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display Real-Time Stock Monitoring Page
     */
    public function realStock(Request $request)
    {
        $search = $request->input('search');
        $kategori = $request->input('kategori');
        $stockStatus = $request->input('stock_status'); // 'all', 'min_stock', 'out_of_stock', 'expiring_soon'

        $query = DB::table('obats');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('kode', 'like', "%{$search}%")
                  ->orWhere('kategori', 'like', "%{$search}%");
            });
        }

        if ($kategori) {
            $query->where('kategori', $kategori);
        }

        if ($stockStatus === 'min_stock') {
            $query->whereColumn('stok', '<=', 'min_stok')->where('stok', '>', 0);
        } elseif ($stockStatus === 'out_of_stock') {
            $query->where('stok', '<=', 0);
        }

        $medicines = $query->orderBy('nama', 'asc')->paginate(25)->withQueryString();

        // Attach Real Batch details & FEFO status to each medicine
        $medicines->getCollection()->transform(function ($item) {
            $item->min_stok = $item->min_stok ?? 10;
            $item->max_stok = $item->max_stok ?? 100;

            $batches = DB::table('medicine_batches')
                ->where('medicine_id', $item->kode)
                ->where('stock', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->get();

            $nearExpiredBatchCount = $batches->filter(function ($b) {
                return Carbon::parse($b->expired_date)->diffInDays(now(), false) >= -60;
            })->count();

            $item->batches = $batches;
            $item->near_expired_count = $nearExpiredBatchCount;
            $item->total_asset_value = $item->stok * $item->harga;

            if ($item->stok <= 0) {
                $item->status_badge = 'OUT_OF_STOCK';
            } elseif ($item->stok <= $item->min_stok) {
                $item->status_badge = 'LOW_STOCK';
            } elseif ($nearExpiredBatchCount > 0) {
                $item->status_badge = 'NEAR_EXPIRED';
            } else {
                $item->status_badge = 'NORMAL';
            }

            return $item;
        });

        // Summary Statistics
        $totalItems = DB::table('obats')->count();
        $totalPhysicalStock = DB::table('obats')->sum('stok');
        $lowStockCount = DB::table('obats')->whereColumn('stok', '<=', 'min_stok')->count();
        $outOfStockCount = DB::table('obats')->where('stok', '<=', 0)->count();
        $totalAssetValuation = DB::table('obats')->select(DB::raw('SUM(stok * harga) as total_val'))->value('total_val') ?? 0;

        $categories = DB::table('obats')->distinct()->pluck('kategori');

        return Inertia::render('Inventory/RealStock', [
            'medicines' => $medicines,
            'summary' => [
                'total_items' => $totalItems,
                'total_physical_stock' => $totalPhysicalStock,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'total_asset_valuation' => $totalAssetValuation,
            ],
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'kategori' => $kategori,
                'stock_status' => $stockStatus,
            ]
        ]);
    }
}
