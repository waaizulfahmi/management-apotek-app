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
        $obats = Obat::when($search, function ($query, $search) {
            return $query->where('nama', 'like', "%{$search}%")
                         ->orWhere('kode', 'like', "%{$search}%");
        })->paginate(5)->withQueryString();
        
        return Inertia::render('Admin/Obat/Index', [
            'obats' => $obats,
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
        ]);

        $data = $request->all();
        $data['stok'] = $request->input('stok', 0);
        $data['min_stok'] = $request->input('min_stok', 10);

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
        ]);

        $data = $request->all();
        if ($request->has('min_stok')) {
            $data['min_stok'] = $request->input('min_stok', 10);
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
        $obat->delete();

        return redirect()->route('admin.obat.index')->with('success', 'Obat berhasil dihapus!');
    }
}
