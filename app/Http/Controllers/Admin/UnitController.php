<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of medicine units.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $units = Unit::when($search, function ($query, $search) {
            return $query->where('name', 'like', "%{$search}%");
        })
        ->orderBy('name', 'asc')
        ->paginate(15)
        ->withQueryString();

        return Inertia::render('Admin/Units/Index', [
            'units' => $units,
            'filters' => [
                'search' => $search,
            ]
        ]);
    }

    /**
     * Store a newly created unit.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('units', 'name')->whereNull('deleted_at'),
            ],
            'is_active' => 'nullable|boolean',
        ]);

        $unit = Unit::create([
            'name' => trim($request->name),
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        $this->auditLogService->log(
            'CREATE_UNIT',
            'Master Satuan',
            null,
            ['id' => $unit->id, 'name' => $unit->name]
        );

        return redirect()->back()->with('success', "Satuan '{$unit->name}' berhasil ditambahkan!");
    }

    /**
     * Update the specified unit.
     */
    public function update(Request $request, string $id)
    {
        $unit = Unit::findOrFail($id);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('units', 'name')->ignore($unit->id)->whereNull('deleted_at'),
            ],
            'is_active' => 'nullable|boolean',
        ]);

        $old = ['name' => $unit->name, 'is_active' => $unit->is_active];

        $unit->update([
            'name' => trim($request->name),
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => auth()->id(),
        ]);

        $this->auditLogService->log(
            'UPDATE_UNIT',
            'Master Satuan',
            $old,
            ['name' => $unit->name, 'is_active' => $unit->is_active]
        );

        return redirect()->back()->with('success', "Satuan '{$unit->name}' berhasil diupdate!");
    }

    /**
     * Toggle active status of a unit.
     */
    public function toggleStatus(string $id)
    {
        $unit = Unit::findOrFail($id);
        $unit->update([
            'is_active' => !$unit->is_active,
            'updated_by' => auth()->id(),
        ]);

        $statusStr = $unit->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $this->auditLogService->log(
            'TOGGLE_UNIT_STATUS',
            'Master Satuan',
            ['is_active' => !$unit->is_active],
            ['is_active' => $unit->is_active]
        );

        return redirect()->back()->with('success', "Satuan '{$unit->name}' berhasil {$statusStr}!");
    }

    /**
     * Remove the specified unit (Soft Delete).
     */
    public function destroy(string $id)
    {
        $unit = Unit::findOrFail($id);
        $unit->update(['deleted_by' => auth()->id()]);
        $unit->delete();

        $this->auditLogService->log(
            'DELETE_UNIT',
            'Master Satuan',
            ['id' => $unit->id, 'name' => $unit->name],
            ['status' => 'SOFT_DELETED']
        );

        return redirect()->back()->with('success', "Satuan '{$unit->name}' berhasil di-soft delete!");
    }
}
