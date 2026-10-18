<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedicineType;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MedicineTypeController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of medicine types (bentuk sediaan obat).
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $types = MedicineType::when($search, function ($query, $search) {
            return $query->where('name', 'like', "%{$search}%")
                         ->orWhere('description', 'like', "%{$search}%");
        })
        ->withCount('obats')
        ->orderBy('name', 'asc')
        ->paginate(15)
        ->withQueryString();

        $stats = [
            'total' => MedicineType::count(),
            'active' => MedicineType::where('is_active', true)->count(),
            'inactive' => MedicineType::where('is_active', false)->count(),
        ];

        return Inertia::render('Admin/MedicineTypes/Index', [
            'types' => $types,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
            ]
        ]);
    }

    /**
     * Store a newly created medicine type.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('medicine_types', 'name')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $name = trim($request->name);

        $type = MedicineType::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $request->description ? trim($request->description) : null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        $this->auditLogService->log(
            'CREATE_MEDICINE_TYPE',
            'Master Jenis Obat',
            null,
            ['id' => $type->id, 'name' => $type->name, 'description' => $type->description]
        );

        return redirect()->back()->with('success', "Jenis Obat '{$type->name}' berhasil ditambahkan!");
    }

    /**
     * Quick store via AJAX for instant creation inside drug modals.
     */
    public function quickStore(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('medicine_types', 'name')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:500',
        ]);

        $name = trim($request->name);

        $type = MedicineType::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $request->description ? trim($request->description) : null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $this->auditLogService->log(
            'QUICK_CREATE_MEDICINE_TYPE',
            'Master Jenis Obat',
            null,
            ['id' => $type->id, 'name' => $type->name]
        );

        return response()->json([
            'success' => true,
            'message' => "Jenis Obat '{$type->name}' berhasil ditambahkan!",
            'data' => $type,
        ]);
    }

    /**
     * Update the specified medicine type.
     */
    public function update(Request $request, string $id)
    {
        $type = MedicineType::findOrFail($id);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('medicine_types', 'name')->ignore($type->id)->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $old = [
            'name' => $type->name,
            'description' => $type->description,
            'is_active' => $type->is_active,
        ];

        $name = trim($request->name);

        $type->update([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $request->description ? trim($request->description) : null,
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => auth()->id(),
        ]);

        $this->auditLogService->log(
            'UPDATE_MEDICINE_TYPE',
            'Master Jenis Obat',
            $old,
            ['name' => $type->name, 'description' => $type->description, 'is_active' => $type->is_active]
        );

        return redirect()->back()->with('success', "Jenis Obat '{$type->name}' berhasil diupdate!");
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(string $id)
    {
        $type = MedicineType::findOrFail($id);
        $type->update([
            'is_active' => !$type->is_active,
            'updated_by' => auth()->id(),
        ]);

        $statusStr = $type->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $this->auditLogService->log(
            'TOGGLE_MEDICINE_TYPE_STATUS',
            'Master Jenis Obat',
            ['is_active' => !$type->is_active],
            ['is_active' => $type->is_active]
        );

        return redirect()->back()->with('success', "Jenis Obat '{$type->name}' berhasil {$statusStr}!");
    }

    /**
     * Remove the specified medicine type (Soft Delete).
     */
    public function destroy(string $id)
    {
        $type = MedicineType::findOrFail($id);
        $type->update(['deleted_by' => auth()->id()]);
        $type->delete();

        $this->auditLogService->log(
            'DELETE_MEDICINE_TYPE',
            'Master Jenis Obat',
            ['id' => $type->id, 'name' => $type->name],
            ['status' => 'SOFT_DELETED']
        );

        return redirect()->back()->with('success', "Jenis Obat '{$type->name}' berhasil dihapus (dapat dipulihkan dari Trash)!");
    }
}
