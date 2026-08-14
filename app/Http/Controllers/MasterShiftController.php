<?php

namespace App\Http\Controllers;

use App\Models\MasterShift;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Exception;

class MasterShiftController extends Controller
{
    public function index()
    {
        $shifts = MasterShift::orderBy('start_time', 'asc')->get();

        return Inertia::render('MasterShifts/Index', [
            'shifts' => $shifts,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'grace_minutes' => 'required|integer|min:0|max:120',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        MasterShift::create([
            'name' => $validated['name'],
            'start_time' => $validated['start_time'] . ':00',
            'end_time' => $validated['end_time'] . ':00',
            'grace_minutes' => $validated['grace_minutes'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return back()->with('success', 'Master Shift berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $shift = MasterShift::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'start_time' => 'required',
            'end_time' => 'required',
            'grace_minutes' => 'required|integer|min:0|max:120',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $startTime = strlen($validated['start_time']) === 5 ? $validated['start_time'] . ':00' : $validated['start_time'];
        $endTime = strlen($validated['end_time']) === 5 ? $validated['end_time'] . ':00' : $validated['end_time'];

        $shift->update([
            'name' => $validated['name'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'grace_minutes' => $validated['grace_minutes'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Master Shift berhasil diperbarui!');
    }

    public function toggleActive($id)
    {
        $shift = MasterShift::findOrFail($id);
        $shift->update(['is_active' => !$shift->is_active]);

        $statusStr = $shift->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Master Shift {$shift->name} berhasil {$statusStr}!");
    }

    public function destroy($id)
    {
        $shift = MasterShift::findOrFail($id);
        $shift->delete();

        return back()->with('success', 'Master Shift berhasil dihapus!');
    }
}
