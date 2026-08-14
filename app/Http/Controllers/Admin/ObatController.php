<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Inertia\Inertia;

class ObatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $obats = Obat::with('supplier')->when($search, function ($query, $search) {
            return $query->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode', 'like', "%{$search}%")
                         ->orWhere('merk', 'like', "%{$search}%")
                         ->orWhere('supplier_name', 'like', "%{$search}%");
        })->paginate(10)->withQueryString();
        
        $suppliers = \App\Models\Supplier::select('id', 'name', 'code')->get();

        return Inertia::render('Admin/Obat/Index', [
            'obats' => $obats,
            'suppliers' => $suppliers,
            'filters' => ['search' => $search]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Not used as we use modal
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (empty($request->kode)) {
            $request->merge(['kode' => 'OBT-' . rand(1000, 9999)]);
        }

        $request->validate([
            'kode' => 'required|unique:obats,kode|max:10',
            'nama' => 'required|max:100',
            'gambar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jenis_obat' => 'required',
            'kategori' => 'required',
            'harga' => 'required|numeric',
            'stok' => 'nullable|integer|min:0',
            'min_stok' => 'nullable|integer|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:150',
            'merk' => 'nullable|string|max:100',
        ]);

        $data = $request->all();
        $data['stok'] = $request->input('stok', 0);
        $data['min_stok'] = $request->input('min_stok', 10);

        if ($request->supplier_id && empty($data['supplier_name'])) {
            $sup = \App\Models\Supplier::find($request->supplier_id);
            if ($sup) $data['supplier_name'] = $sup->name;
        }

        if ($request->hasFile('gambar')) {
            $imageName = time().'.'.$request->gambar->extension();  
            $request->gambar->move(public_path('Assets/Obat'), $imageName);
            $data['gambar'] = $imageName;
        }

        Obat::create($data);

        return redirect()->route('admin.obat.index')->with('success', 'Obat berhasil ditambahkan!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $obat = Obat::findOrFail($id);

        $request->validate([
            'nama' => 'required|max:100',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jenis_obat' => 'required',
            'kategori' => 'required',
            'harga' => 'required|numeric',
            'stok' => 'nullable|integer|min:0',
            'min_stok' => 'nullable|integer|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'supplier_name' => 'nullable|string|max:150',
            'merk' => 'nullable|string|max:100',
        ]);

        $data = $request->all();
        if ($request->has('min_stok')) {
            $data['min_stok'] = $request->input('min_stok', 10);
        }

        if ($request->supplier_id && empty($data['supplier_name'])) {
            $sup = \App\Models\Supplier::find($request->supplier_id);
            if ($sup) $data['supplier_name'] = $sup->name;
        }

        if ($request->hasFile('gambar')) {
            $imageName = time().'.'.$request->gambar->extension();  
            $request->gambar->move(public_path('Assets/Obat'), $imageName);
            $data['gambar'] = $imageName;
        }

        $obat->update($data);

        return redirect()->route('admin.obat.index')->with('success', 'Obat berhasil diupdate!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $obat = Obat::findOrFail($id);
        $obat->update(['deleted_by' => auth()->id()]);
        $obat->delete();

        app(\App\Services\AuditLogService::class)->log(
            'DELETE',
            'Produk',
            ['kode' => $obat->kode, 'nama' => $obat->nama],
            ['status' => 'SOFT_DELETED']
        );

        return redirect()->route('admin.obat.index')->with('success', 'Obat berhasil di-soft delete! Data dapat dipulihkan melalui menu Trash.');
    }
}
