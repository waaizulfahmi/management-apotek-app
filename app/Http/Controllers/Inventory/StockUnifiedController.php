<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\Outlet;
use App\Services\OutletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockUnifiedController extends Controller
{
    /**
     * Tampilan Gabungan Stok & Produk Outlet
     * Menggabungkan fitur: Stok Real Time (Global & FEFO), Stok Per Outlet, dan Katalog Produk Per Outlet.
     */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'outlet'); // 'outlet' | 'realtime'
        $user = auth()->user();
        $outlets = OutletService::getUserOutlets($user);

        // Requested or active outlet
        $requestedOutletId = $request->input('outlet_id');
        if ($requestedOutletId && OutletService::userCanAccessOutlet($user, (int)$requestedOutletId)) {
            $outletId = (int)$requestedOutletId;
        } else {
            $outletId = OutletService::getActiveOutletId($user);
        }

        $selectedOutlet = Outlet::find($outletId) ?: $outlets->first();
        $activeOutletId = $selectedOutlet ? $selectedOutlet->id : null;

        if ($activeOutletId) {
            session(['active_outlet_id' => $activeOutletId]);
        }

        $categories = DB::table('obats')->whereNotNull('kategori')->distinct()->pluck('kategori');

        // ==========================================
        // TAB 1: STOK & PRODUK PER OUTLET (CABANG)
        // ==========================================
        if ($tab === 'outlet') {
            $search = $request->input('search');
            $kategori = $request->input('kategori');
            $status = $request->input('status', 'ALL');
            $stockFilter = $request->input('stock_filter', 'all'); // 'all', 'safe', 'low', 'empty'

            $query = DB::table('product_outlets')
                ->join('obats', 'product_outlets.obat_id', '=', 'obats.kode')
                ->leftJoin('product_stocks', function ($join) use ($activeOutletId) {
                    $join->on('product_outlets.obat_id', '=', 'product_stocks.obat_id')
                         ->where('product_stocks.outlet_id', '=', $activeOutletId);
                })
                ->where('product_outlets.outlet_id', $activeOutletId)
                ->whereNull('product_outlets.deleted_at')
                ->whereNull('obats.deleted_at')
                ->select(
                    'product_outlets.id as relation_id',
                    'product_outlets.obat_id',
                    'product_outlets.outlet_id',
                    'product_outlets.is_active as outlet_is_active',
                    'product_outlets.price as custom_price',
                    'obats.nama',
                    'obats.jenis_obat',
                    'obats.kategori',
                    'obats.harga as default_price',
                    'obats.stok as main_stok',
                    DB::raw('COALESCE(product_stocks.min_stock, obats.min_stok, 5) as min_stock'),
                    DB::raw('COALESCE(product_stocks.stock, 0) as current_stock'),
                    'product_stocks.rack_location'
                );

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('obats.nama', 'like', "%{$search}%")
                      ->orWhere('obats.kode', 'like', "%{$search}%");
                });
            }

            if ($kategori) {
                $query->where('obats.kategori', $kategori);
            }

            if ($status === 'ACTIVE') {
                $query->where('product_outlets.is_active', true);
            } elseif ($status === 'INACTIVE') {
                $query->where('product_outlets.is_active', false);
            }

            if ($stockFilter === 'low') {
                $query->whereRaw('COALESCE(product_stocks.stock, 0) <= COALESCE(product_stocks.min_stock, obats.min_stok, 5)')
                      ->whereRaw('COALESCE(product_stocks.stock, 0) > 0');
            } elseif ($stockFilter === 'empty') {
                $query->whereRaw('COALESCE(product_stocks.stock, 0) <= 0');
            } elseif ($stockFilter === 'safe') {
                $query->whereRaw('COALESCE(product_stocks.stock, 0) > COALESCE(product_stocks.min_stock, obats.min_stok, 5)');
            }

            $products = $query->orderBy('obats.nama', 'asc')->paginate(20)->withQueryString();

            // Outlet Summary Stats
            $outletBaseQuery = DB::table('product_outlets')
                ->join('obats', 'product_outlets.obat_id', '=', 'obats.kode')
                ->leftJoin('product_stocks', function ($join) use ($activeOutletId) {
                    $join->on('product_outlets.obat_id', '=', 'product_stocks.obat_id')
                         ->where('product_stocks.outlet_id', '=', $activeOutletId);
                })
                ->where('product_outlets.outlet_id', $activeOutletId)
                ->whereNull('product_outlets.deleted_at')
                ->whereNull('obats.deleted_at');

            $outletStats = [
                'total_products' => (clone $outletBaseQuery)->count(),
                'safe_stock' => (clone $outletBaseQuery)->whereRaw('COALESCE(product_stocks.stock, 0) > COALESCE(product_stocks.min_stock, obats.min_stok, 5)')->count(),
                'low_stock' => (clone $outletBaseQuery)->whereRaw('COALESCE(product_stocks.stock, 0) <= COALESCE(product_stocks.min_stock, obats.min_stok, 5)')->whereRaw('COALESCE(product_stocks.stock, 0) > 0')->count(),
                'empty_stock' => (clone $outletBaseQuery)->whereRaw('COALESCE(product_stocks.stock, 0) <= 0')->count(),
                'active_products' => (clone $outletBaseQuery)->where('product_outlets.is_active', true)->count(),
            ];

            // Global Products not yet registered in this outlet (for Add Modal)
            $registeredKodes = DB::table('product_outlets')
                ->where('outlet_id', $activeOutletId)
                ->whereNull('deleted_at')
                ->pluck('obat_id')
                ->toArray();

            $availableGlobalProducts = Obat::select('kode', 'nama', 'jenis_obat', 'kategori', 'harga')
                ->whereNotIn('kode', $registeredKodes)
                ->whereNull('deleted_at')
                ->orderBy('nama', 'asc')
                ->get();

            return Inertia::render('Inventory/StockUnified', [
                'activeTab' => 'outlet',
                'products' => $products,
                'outlets' => $outlets,
                'selectedOutlet' => $selectedOutlet,
                'availableGlobalProducts' => $availableGlobalProducts,
                'categories' => $categories,
                'outletStats' => $outletStats,
                'filters' => [
                    'tab' => 'outlet',
                    'outlet_id' => $activeOutletId,
                    'search' => $search ?? '',
                    'kategori' => $kategori ?? '',
                    'status' => $status ?? 'ALL',
                    'stock_filter' => $stockFilter ?? 'all',
                ]
            ]);
        }

        // ==========================================
        // TAB 2: STOK REAL TIME & BATCH FEFO (DENGAN IDENTIFIKASI OUTLET)
        // ==========================================
        $search = $request->input('search');
        $kategori = $request->input('kategori');
        $stockStatus = $request->input('stock_status'); // 'all', 'min_stock', 'out_of_stock'
        $realtimeOutletId = $request->input('realtime_outlet_id', 'all');

        $selectedRealtimeOutlet = null;
        if ($realtimeOutletId !== 'all' && is_numeric($realtimeOutletId)) {
            $selectedRealtimeOutlet = Outlet::find($realtimeOutletId);
        }

        if ($selectedRealtimeOutlet) {
            // Specific Outlet Query
            $rtOutletId = $selectedRealtimeOutlet->id;
            $query = DB::table('obats')
                ->leftJoin('product_stocks', function ($join) use ($rtOutletId) {
                    $join->on('obats.kode', '=', 'product_stocks.obat_id')
                         ->where('product_stocks.outlet_id', '=', $rtOutletId);
                })
                ->whereNull('obats.deleted_at')
                ->select(
                    'obats.kode',
                    'obats.nama',
                    'obats.jenis_obat',
                    'obats.kategori',
                    'obats.harga',
                    'obats.min_stok',
                    'obats.max_stok',
                    'obats.stok as global_stok',
                    DB::raw('COALESCE(product_stocks.stock, 0) as stok'),
                    DB::raw('COALESCE(product_stocks.min_stock, obats.min_stok, 5) as outlet_min_stok'),
                    'product_stocks.rack_location'
                );

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('obats.nama', 'like', "%{$search}%")
                      ->orWhere('obats.kode', 'like', "%{$search}%")
                      ->orWhere('obats.kategori', 'like', "%{$search}%");
                });
            }

            if ($kategori) {
                $query->where('obats.kategori', $kategori);
            }

            if ($stockStatus === 'min_stock') {
                $query->whereRaw('COALESCE(product_stocks.stock, 0) <= COALESCE(product_stocks.min_stock, obats.min_stok, 5)')
                      ->whereRaw('COALESCE(product_stocks.stock, 0) > 0');
            } elseif ($stockStatus === 'out_of_stock') {
                $query->whereRaw('COALESCE(product_stocks.stock, 0) <= 0');
            }

            $medicines = $query->orderBy('obats.nama', 'asc')->paginate(25)->withQueryString();

            // Summary Statistics for this selected outlet
            $totalItems = DB::table('obats')->whereNull('deleted_at')->count();
            $totalPhysicalStock = DB::table('product_stocks')->where('outlet_id', $rtOutletId)->sum('stock');
            $lowStockCount = DB::table('product_stocks')
                ->join('obats', 'product_stocks.obat_id', '=', 'obats.kode')
                ->where('product_stocks.outlet_id', $rtOutletId)
                ->whereNull('obats.deleted_at')
                ->whereRaw('COALESCE(product_stocks.stock, 0) <= COALESCE(product_stocks.min_stock, obats.min_stok, 5)')
                ->whereRaw('COALESCE(product_stocks.stock, 0) > 0')
                ->count();
            $outOfStockCount = DB::table('product_stocks')
                ->where('outlet_id', $rtOutletId)
                ->where('stock', '<=', 0)
                ->count();
            $totalAssetValuation = DB::table('product_stocks')
                ->join('obats', 'product_stocks.obat_id', '=', 'obats.kode')
                ->where('product_stocks.outlet_id', $rtOutletId)
                ->whereNull('obats.deleted_at')
                ->select(DB::raw('SUM(product_stocks.stock * obats.harga) as total_val'))
                ->value('total_val') ?? 0;
        } else {
            // Global (All Outlets) Query - Calculated dynamically from product_stocks
            $query = DB::table('obats')
                ->whereNull('deleted_at')
                ->select(
                    'kode',
                    'nama',
                    'jenis_obat',
                    'kategori',
                    'harga',
                    'min_stok',
                    'max_stok',
                    DB::raw('COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_stocks.obat_id = obats.kode), obats.stok, 0) as stok')
                );

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
                $query->whereRaw('COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_stocks.obat_id = obats.kode), obats.stok, 0) <= COALESCE(min_stok, 5)')
                      ->whereRaw('COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_stocks.obat_id = obats.kode), obats.stok, 0) > 0');
            } elseif ($stockStatus === 'out_of_stock') {
                $query->whereRaw('COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_stocks.obat_id = obats.kode), obats.stok, 0) <= 0');
            }

            $medicines = $query->orderBy('nama', 'asc')->paginate(25)->withQueryString();

            // Summary Statistics for all outlets (Real physical stock sum)
            $totalItems = DB::table('obats')->whereNull('deleted_at')->count();
            $totalPhysicalStock = DB::table('product_stocks')->sum('stock');
            $lowStockCount = DB::table('obats')
                ->whereNull('deleted_at')
                ->whereRaw('COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_stocks.obat_id = obats.kode), obats.stok, 0) <= COALESCE(min_stok, 5)')
                ->whereRaw('COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_stocks.obat_id = obats.kode), obats.stok, 0) > 0')
                ->count();
            $outOfStockCount = DB::table('obats')
                ->whereNull('deleted_at')
                ->whereRaw('COALESCE((SELECT SUM(stock) FROM product_stocks WHERE product_stocks.obat_id = obats.kode), obats.stok, 0) <= 0')
                ->count();
            $totalAssetValuation = DB::table('product_stocks')
                ->join('obats', 'product_stocks.obat_id', '=', 'obats.kode')
                ->whereNull('obats.deleted_at')
                ->select(DB::raw('SUM(product_stocks.stock * obats.harga) as total_val'))
                ->value('total_val') ?? 0;
        }

        // Fetch Outlet Breakdown for the 25 medicines on the page
        $medicineCodes = $medicines->pluck('kode')->toArray();
        $outletStocksRaw = DB::table('product_stocks')
            ->join('outlets', 'product_stocks.outlet_id', '=', 'outlets.id')
            ->whereIn('product_stocks.obat_id', $medicineCodes)
            ->whereNull('outlets.deleted_at')
            ->select('product_stocks.obat_id', 'product_stocks.outlet_id', 'product_stocks.stock', 'outlets.name as outlet_name', 'outlets.code as outlet_code', 'outlets.is_main')
            ->orderBy('outlets.is_main', 'desc')
            ->orderBy('outlets.name', 'asc')
            ->get()
            ->groupBy('obat_id');

        // Attach Real Batch details & FEFO status & outlet breakdown to each medicine
        $medicines->getCollection()->transform(function ($item) use ($outletStocksRaw, $selectedRealtimeOutlet) {
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

            // Outlet Breakdown
            $item->outlet_breakdown = $outletStocksRaw->get($item->kode, collect())->map(function ($os) {
                return [
                    'outlet_id' => $os->outlet_id,
                    'outlet_name' => $os->outlet_name,
                    'outlet_code' => $os->outlet_code,
                    'is_main' => (bool)$os->is_main,
                    'stock' => (float)$os->stock,
                ];
            })->values();

            // When viewing global (all outlets), ensure stok precisely equals the sum of outlets
            if (!$selectedRealtimeOutlet && $item->outlet_breakdown->isNotEmpty()) {
                $item->stok = $item->outlet_breakdown->sum('stock');
            }

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

        return Inertia::render('Inventory/StockUnified', [
            'activeTab' => 'realtime',
            'medicines' => $medicines,
            'summary' => [
                'total_items' => $totalItems,
                'total_physical_stock' => $totalPhysicalStock,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'total_asset_valuation' => $totalAssetValuation,
            ],
            'outlets' => $outlets,
            'selectedOutlet' => $selectedOutlet,
            'selectedRealtimeOutlet' => $selectedRealtimeOutlet,
            'categories' => $categories,
            'filters' => [
                'tab' => 'realtime',
                'search' => $search ?? '',
                'kategori' => $kategori ?? '',
                'stock_status' => $stockStatus ?? '',
                'realtime_outlet_id' => $realtimeOutletId,
            ]
        ]);
    }
}
