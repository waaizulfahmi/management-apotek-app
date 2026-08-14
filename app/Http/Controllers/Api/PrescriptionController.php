<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class PrescriptionController extends Controller
{
    /**
     * Display prescription list with search and filter
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $query = DB::table('prescriptions')
            ->leftJoin('customers', 'prescriptions.customer_id', '=', 'customers.id')
            ->leftJoin('doctors', 'prescriptions.doctor_id', '=', 'doctors.id')
            ->leftJoin('users', 'prescriptions.pharmacist_id', '=', 'users.id')
            ->leftJoin('sales', 'sales.prescription_id', '=', 'prescriptions.id')
            ->select(
                'prescriptions.*',
                'customers.name as patient_name',
                'customers.phone as patient_phone',
                'doctors.name as doctor_name',
                'doctors.specialty as doctor_specialty',
                'users.name as pharmacist_name',
                'sales.invoice_number as sale_invoice_number'
            );

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('prescriptions.prescription_number', 'like', "%{$search}%")
                  ->orWhere('customers.name', 'like', "%{$search}%")
                  ->orWhere('doctors.name', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('prescriptions.status', $status);
        }

        $prescriptions = $query->orderBy('prescriptions.id', 'desc')->paginate(10)->withQueryString();

        foreach ($prescriptions as $rx) {
            $rx->items = DB::table('prescription_items')
                ->join('obats', 'prescription_items.medicine_id', '=', 'obats.kode')
                ->select(
                    'prescription_items.*',
                    'obats.nama as medicine_name',
                    'obats.jenis_obat as medicine_unit',
                    'obats.harga as unit_price',
                    'obats.stok as stock'
                )
                ->where('prescription_items.prescription_id', $rx->id)
                ->get();
        }

        $customers = DB::table('customers')->get();
        $doctors = DB::table('doctors')->get();
        $medicines = DB::table('obats')->get();

        return Inertia::render('Prescriptions/Index', [
            'prescriptions' => $prescriptions,
            'customers' => $customers,
            'doctors' => $doctors,
            'medicines' => $medicines,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ]
        ]);
    }

    /**
     * Show single prescription detail (JSON)
     */
    public function show($id)
    {
        $rx = DB::table('prescriptions')
            ->leftJoin('customers', 'prescriptions.customer_id', '=', 'customers.id')
            ->leftJoin('doctors', 'prescriptions.doctor_id', '=', 'doctors.id')
            ->leftJoin('users', 'prescriptions.pharmacist_id', '=', 'users.id')
            ->leftJoin('sales', 'sales.prescription_id', '=', 'prescriptions.id')
            ->select(
                'prescriptions.*',
                'customers.name as patient_name',
                'customers.phone as patient_phone',
                'doctors.name as doctor_name',
                'doctors.specialty as doctor_specialty',
                'users.name as pharmacist_name',
                'sales.invoice_number as sale_invoice_number'
            )
            ->where('prescriptions.id', $id)
            ->first();

        if (!$rx) abort(404);

        $items = DB::table('prescription_items')
            ->join('obats', 'prescription_items.medicine_id', '=', 'obats.kode')
            ->select(
                'prescription_items.*',
                'obats.nama as medicine_name',
                'obats.jenis_obat as medicine_unit',
                'obats.harga as unit_price',
                'obats.stok as stock'
            )
            ->where('prescription_items.prescription_id', $id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'prescription' => $rx,
                'items' => $items,
            ]
        ]);
    }

    /**
     * Store new prescription
     */
    public function store(Request $request)
    {
        $request->validate([
            'prescription_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:obats,kode',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $rxNumber = 'RX-' . date('YmdHis') . '-' . rand(100, 999);

            $rxId = DB::table('prescriptions')->insertGetId([
                'prescription_number' => $rxNumber,
                'customer_id' => $request->customer_id ?: null,
                'doctor_id' => $request->doctor_id ?: null,
                'pharmacist_id' => auth()->id(),
                'prescription_date' => $request->prescription_date,
                'status' => $request->status ?? 'verified',
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $item) {
                DB::table('prescription_items')->insert([
                    'prescription_id' => $rxId,
                    'medicine_id' => $item['medicine_id'],
                    'dosage' => $item['dosage'] ?? '500mg',
                    'frequency' => $item['frequency'] ?? '3x1 Sehari',
                    'duration' => $item['duration'] ?? '5 Hari',
                    'quantity' => $item['quantity'],
                    'instructions' => $item['instructions'] ?? 'Diminum sesudah makan',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return redirect()->route('prescriptions.index')->with('success', "Resep Dokter {$rxNumber} berhasil didaftarkan!");
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update existing prescription
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'prescription_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:obats,kode',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            DB::table('prescriptions')->where('id', $id)->update([
                'customer_id' => $request->customer_id ?: null,
                'doctor_id' => $request->doctor_id ?: null,
                'prescription_date' => $request->prescription_date,
                'status' => $request->status ?? 'verified',
                'notes' => $request->notes,
                'updated_at' => now(),
            ]);

            // Replace items
            DB::table('prescription_items')->where('prescription_id', $id)->delete();

            foreach ($request->items as $item) {
                DB::table('prescription_items')->insert([
                    'prescription_id' => $id,
                    'medicine_id' => $item['medicine_id'],
                    'dosage' => $item['dosage'] ?? '500mg',
                    'frequency' => $item['frequency'] ?? '3x1 Sehari',
                    'duration' => $item['duration'] ?? '5 Hari',
                    'quantity' => $item['quantity'],
                    'instructions' => $item['instructions'] ?? 'Diminum sesudah makan',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return redirect()->route('prescriptions.index')->with('success', 'Resep Dokter berhasil diperbarui!');
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete prescription
     */
    public function destroy($id)
    {
        DB::table('prescriptions')->where('id', $id)->delete();
        return redirect()->route('prescriptions.index')->with('success', 'Resep Dokter berhasil dihapus.');
    }

    /**
     * Change status (e.g. verified, dispensed, completed)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|string']);
        DB::table('prescriptions')->where('id', $id)->update([
            'status' => $request->status,
            'updated_at' => now(),
        ]);
        return redirect()->back()->with('success', 'Status Resep berhasil diperbarui.');
    }
}
