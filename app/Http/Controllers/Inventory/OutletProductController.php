<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\Outlet;
use App\Models\ProductOutlet;
use App\Models\ProductStock;
use App\Services\OutletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class OutletProductController extends Controller
{
    /**
     * Manajemen Produk Per Outlet
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $outlets = OutletService::getUserOutlets($user);

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

        $search = $request->input('search');
        $kategori = $request->input('kategori');
        $status = $request->input('status', 'ALL');

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
                'obats.min_stok',
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

        $products = $query->orderBy('obats.nama', 'asc')->paginate(20)->withQueryString();

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

        $categories = DB::table('obats')->whereNotNull('kategori')->distinct()->pluck('kategori');

        return Inertia::render('Inventory/OutletProducts/Index', [
            'products' => $products,
            'outlets' => $outlets,
            'selectedOutlet' => $selectedOutlet,
            'availableGlobalProducts' => $availableGlobalProducts,
            'categories' => $categories,
            'filters' => [
                'outlet_id' => $activeOutletId,
                'search' => $search ?? '',
                'kategori' => $kategori ?? '',
                'status' => $status ?? 'ALL',
            ]
        ]);
    }

    /**
     * Tambahkan Produk Global ke Outlet
     */
    public function attach(Request $request)
    {
        $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'obat_kodes' => 'required|array|min:1',
            'obat_kodes.*' => 'exists:obats,kode',
        ]);

        $count = 0;
        foreach ($request->obat_kodes as $kode) {
            OutletService::attachProductToOutlet($kode, $request->outlet_id);
            $count++;
        }

        $outlet = Outlet::find($request->outlet_id);
        return redirect()->back()->with('success', "{$count} produk berhasil ditambahkan ke outlet \"{$outlet->name}\"!");
    }

    /**
     * Update harga khusus outlet
     */
    public function updatePrice(Request $request, $id)
    {
        $po = ProductOutlet::findOrFail($id);

        $request->validate([
            'price' => 'nullable|numeric|min:0',
        ]);

        $po->update([
            'price' => $request->price !== null && $request->price !== '' ? $request->price : null,
        ]);

        return redirect()->back()->with('success', 'Harga khusus outlet berhasil diperbarui!');
    }

    /**
     * Toggle status aktif produk di outlet
     */
    public function toggleStatus($id)
    {
        $po = ProductOutlet::findOrFail($id);
        $po->update([
            'is_active' => !$po->is_active,
        ]);

        $statusStr = $po->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->back()->with('success', "Status produk di outlet berhasil {$statusStr}.");
    }

    /**
     * Hapus relasi produk dari outlet (Soft Delete)
     */
    public function detach($id)
    {
        $po = ProductOutlet::findOrFail($id);
        $po->delete();

        return redirect()->back()->with('success', 'Produk berhasil dihapus dari outlet ini (Master produk global tetap aman).');
    }
}
