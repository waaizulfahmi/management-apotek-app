<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $suppliers = DB::table('suppliers')
            ->when($search, function ($q, $search) {
                return $q->where('name', 'like', "%{$search}%")
                         ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => ['search' => $search]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:suppliers,code',
            'name' => 'required|string|max:150',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
        ]);

        DB::table('suppliers')->insert([
            'code' => $request->code,
            'name' => $request->name,
            'contact_person' => $request->contact_person,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'npwp' => $request->npwp,
            'bank_name' => $request->bank_name,
            'bank_account' => $request->bank_account,
            'payment_terms_days' => $request->payment_terms_days ?? 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier baru berhasil ditambahkan!');
    }
}
