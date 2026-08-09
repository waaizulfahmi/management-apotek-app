<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $customers = DB::table('customers')
            ->when($search, function ($q, $search) {
                return $q->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:customers,code',
            'name' => 'required|string|max:150',
            'phone' => 'required|string',
        ]);

        DB::table('customers')->insert([
            'code' => $request->code,
            'name' => $request->name,
            'gender' => $request->gender ?? 'L',
            'date_of_birth' => $request->date_of_birth,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'medical_notes' => $request->medical_notes,
            'allergies' => $request->allergies,
            'membership_level' => $request->membership_level ?? 'regular',
            'points' => 0,
            'total_spending' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('customers.index')->with('success', 'Pelanggan / Member baru berhasil didaftarkan!');
    }
}
