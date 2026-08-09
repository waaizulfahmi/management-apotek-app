<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class PrescriptionController extends Controller
{
    public function index()
    {
        $prescriptions = DB::table('prescriptions')
            ->leftJoin('customers', 'prescriptions.customer_id', '=', 'customers.id')
            ->leftJoin('doctors', 'prescriptions.doctor_id', '=', 'doctors.id')
            ->leftJoin('users', 'prescriptions.pharmacist_id', '=', 'users.id')
            ->select('prescriptions.*', 'customers.name as patient_name', 'doctors.name as doctor_name', 'users.name as pharmacist_name')
            ->orderBy('prescriptions.id', 'desc')
            ->paginate(10);

        $customers = DB::table('customers')->get();
        $doctors = DB::table('doctors')->get();
        $medicines = DB::table('obats')->get();

        return Inertia::render('Prescriptions/Index', [
            'prescriptions' => $prescriptions,
            'customers' => $customers,
            'doctors' => $doctors,
            'medicines' => $medicines,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'prescription_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:obats,kode',
            'items.*.dosage' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $rxNumber = 'RX-' . date('YmdHis') . '-' . rand(100, 999);

            $rxId = DB::table('prescriptions')->insertGetId([
                'prescription_number' => $rxNumber,
                'customer_id' => $request->customer_id,
                'doctor_id' => $request->doctor_id,
                'pharmacist_id' => auth()->id(),
                'prescription_date' => $request->prescription_date,
                'status' => 'verified',
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $item) {
                DB::table('prescription_items')->insert([
                    'prescription_id' => $rxId,
                    'medicine_id' => $item['medicine_id'],
                    'dosage' => $item['dosage'],
                    'frequency' => $item['frequency'] ?? '3x1 Sehari',
                    'duration' => $item['duration'] ?? '5 Hari',
                    'quantity' => $item['quantity'],
                    'instructions' => $item['instructions'] ?? 'Diminum sesudah makan',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return redirect()->route('prescriptions.index')->with('success', 'Resep Dokter berhasil didaftarkan dan diverifikasi!');
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
