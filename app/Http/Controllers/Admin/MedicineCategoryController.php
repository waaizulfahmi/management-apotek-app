<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedicineCategory;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MedicineCategoryController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display a listing of medicine categories (kategori farmakologi obat).
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $categories = MedicineCategory::when($search, function ($query, $search) {
            return $query->where('name', 'like', "%{$search}%")
                         ->orWhere('description', 'like', "%{$search}%");
        })
        ->withCount('obats')
        ->orderBy('name', 'asc')
        ->paginate(15)
        ->withQueryString();

        $stats = [
            'total' => MedicineCategory::count(),
            'active' => MedicineCategory::where('is_active', true)->count(),
            'inactive' => MedicineCategory::where('is_active', false)->count(),
        ];

        return Inertia::render('Admin/MedicineCategories/Index', [
            'categories' => $categories,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
            ]
        ]);
    }

    /**
     * Store a newly created medicine category.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('medicine_categories', 'name')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $name = trim($request->name);

        $category = MedicineCategory::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $request->description ? trim($request->description) : null,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
        ]);

        $this->auditLogService->log(
            'CREATE_MEDICINE_CATEGORY',
            'Master Kategori Obat',
            null,
            ['id' => $category->id, 'name' => $category->name, 'description' => $category->description]
        );

        return redirect()->back()->with('success', "Kategori Obat '{$category->name}' berhasil ditambahkan!");
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
                Rule::unique('medicine_categories', 'name')->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:500',
        ]);

        $name = trim($request->name);

        $category = MedicineCategory::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $request->description ? trim($request->description) : null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        $this->auditLogService->log(
            'QUICK_CREATE_MEDICINE_CATEGORY',
            'Master Kategori Obat',
            null,
            ['id' => $category->id, 'name' => $category->name]
        );

        return response()->json([
            'success' => true,
            'message' => "Kategori Obat '{$category->name}' berhasil ditambahkan!",
            'data' => $category,
        ]);
    }

    /**
     * Update the specified medicine category.
     */
    public function update(Request $request, string $id)
    {
        $category = MedicineCategory::findOrFail($id);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('medicine_categories', 'name')->ignore($category->id)->whereNull('deleted_at'),
            ],
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $old = [
            'name' => $category->name,
            'description' => $category->description,
            'is_active' => $category->is_active,
        ];

        $name = trim($request->name);

        $category->update([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $request->description ? trim($request->description) : null,
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => auth()->id(),
        ]);

        $this->auditLogService->log(
            'UPDATE_MEDICINE_CATEGORY',
            'Master Kategori Obat',
            $old,
            ['name' => $category->name, 'description' => $category->description, 'is_active' => $category->is_active]
        );

        return redirect()->back()->with('success', "Kategori Obat '{$category->name}' berhasil diupdate!");
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(string $id)
    {
        $category = MedicineCategory::findOrFail($id);
        $category->update([
            'is_active' => !$category->is_active,
            'updated_by' => auth()->id(),
        ]);

        $statusStr = $category->is_active ? 'diaktifkan' : 'dinonaktifkan';

        $this->auditLogService->log(
            'TOGGLE_MEDICINE_CATEGORY_STATUS',
            'Master Kategori Obat',
            ['is_active' => !$category->is_active],
            ['is_active' => $category->is_active]
        );

        return redirect()->back()->with('success', "Kategori Obat '{$category->name}' berhasil {$statusStr}!");
    }

    /**
     * Remove the specified medicine category (Soft Delete).
     */
    public function destroy(string $id)
    {
        $category = MedicineCategory::findOrFail($id);
        $category->update(['deleted_by' => auth()->id()]);
        $category->delete();

        $this->auditLogService->log(
            'DELETE_MEDICINE_CATEGORY',
            'Master Kategori Obat',
            ['id' => $category->id, 'name' => $category->name],
            ['status' => 'SOFT_DELETED']
        );

        return redirect()->back()->with('success', "Kategori Obat '{$category->name}' berhasil dihapus (dapat dipulihkan dari Trash)!");
    }
}
