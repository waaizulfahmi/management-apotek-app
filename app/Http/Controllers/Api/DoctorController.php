<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $doctors = DB::table('doctors')
            ->when($search, function ($q, $search) {
                return $q->where('name', 'like', "%{$search}%")
                         ->orWhere('specialty', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->paginate(10)->withQueryString();

        return Inertia::render('Doctors/Index', [
            'doctors' => $doctors,
            'filters' => ['search' => $search]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:doctors,code',
            'name' => 'required|string|max:150',
            'license_number' => 'required|string',
        ]);

        DB::table('doctors')->insert([
            'code' => $request->code,
            'name' => $request->name,
            'license_number' => $request->license_number,
            'specialty' => $request->specialty,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('doctors.index')->with('success', 'Dokter baru berhasil terdaftar!');
    }
}
