<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

use Inertia\Inertia;

class KasirController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $kasirs = User::where('role', 'kasir')
            ->when($search, function ($query, $search) {
                return $query->where('nama', 'like', "%{$search}%")
                             ->orWhere('username', 'like', "%{$search}%");
            })->paginate(5)->withQueryString();
        
        return Inertia::render('Admin/Kasir/Index', [
            'kasirs' => $kasirs,
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
        $request->validate([
            'nama' => 'required|max:100',
            'username' => 'required|unique:users,username|max:50',
            'password' => 'required|min:4',
            'email' => 'required|email|unique:users,email',
            'no_hp' => 'required|max:15',
            'profil' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->all();
        $data['role'] = 'kasir';
        $data['password'] = Hash::make($request->password);

        if ($request->hasFile('profil')) {
            $imageName = time().'.'.$request->profil->extension();  
            $request->profil->move(public_path('Assets/Kasir'), $imageName);
            $data['profil'] = $imageName;
        }

        User::create($data);

        return redirect()->route('admin.kasir.index')->with('success', 'Kasir berhasil ditambahkan!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $kasir = User::where('role', 'kasir')->findOrFail($id);

        $request->validate([
            'nama' => 'required|max:100',
            'username' => ['required', 'max:50', Rule::unique('users')->ignore($kasir->id)],
            'email' => ['required', 'email', Rule::unique('users')->ignore($kasir->id)],
            'password' => 'nullable|min:4',
            'no_hp' => 'required|max:15',
            'profil' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->except('password');
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('profil')) {
            $imageName = time().'.'.$request->profil->extension();  
            $request->profil->move(public_path('Assets/Kasir'), $imageName);
            $data['profil'] = $imageName;
        }

        $kasir->update($data);

        return redirect()->route('admin.kasir.index')->with('success', 'Kasir berhasil diupdate!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $kasir = User::where('role', 'kasir')->findOrFail($id);
        $kasir->delete();

        return redirect()->route('admin.kasir.index')->with('success', 'Kasir berhasil dihapus!');
    }
}
