<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Obat;
use App\Models\Customer;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\ProductReturn;
use App\Models\StockOpname;
use App\Models\Voucher;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class TrashManagerController extends Controller
{
    private function getModuleMap()
    {
        return [
            'Produk' => [
                'model' => Obat::class,
                'id_key' => 'kode',
                'name_key' => 'nama',
                'unique_fields' => ['kode'],
            ],
            'Customer' => [
                'model' => Customer::class,
                'id_key' => 'id',
                'code_key' => 'code',
                'name_key' => 'name',
                'unique_fields' => ['code', 'email'],
            ],
            'User' => [
                'model' => User::class,
                'id_key' => 'id',
                'code_key' => 'email',
                'name_key' => 'name',
                'unique_fields' => ['email', 'username'],
            ],
            'Supplier' => [
                'model' => Supplier::class,
                'id_key' => 'id',
                'code_key' => 'id',
                'name_key' => 'nama_supplier',
                'unique_fields' => [],
            ],
            'Penjualan' => [
                'model' => Sale::class,
                'id_key' => 'id',
                'code_key' => 'invoice_number',
                'name_key' => 'invoice_number',
                'unique_fields' => [],
            ],
            'Pembelian' => [
                'model' => Purchase::class,
                'id_key' => 'id',
                'code_key' => 'po_number',
                'name_key' => 'po_number',
                'unique_fields' => [],
            ],
            'Retur' => [
                'model' => ProductReturn::class,
                'id_key' => 'id',
                'code_key' => 'return_number',
                'name_key' => 'return_number',
                'unique_fields' => [],
            ],
            'StokOpname' => [
                'model' => StockOpname::class,
                'id_key' => 'id',
                'code_key' => 'opname_number',
                'name_key' => 'opname_number',
                'unique_fields' => [],
            ],
            'Voucher' => [
                'model' => Voucher::class,
                'id_key' => 'id',
                'code_key' => 'code',
                'name_key' => 'name',
                'unique_fields' => ['code'],
            ],
        ];
    }

    /**
     * Display Trash List View with Filters
     */
    public function index(Request $request)
    {
        $selectedModule = $request->input('module');
        $search = $request->input('search');
        $moduleMap = $this->getModuleMap();

        $allTrashItems = collect();

        foreach ($moduleMap as $modName => $config) {
            if ($selectedModule && $selectedModule !== $modName) {
                continue;
            }

            $modelClass = $config['model'];
            $query = $modelClass::onlyTrashed()->with('deletedBy');

            if ($search) {
                $query->where(function($q) use ($config, $search) {
                    $nameKey = $config['name_key'] ?? 'name';
                    $q->where($nameKey, 'like', "%{$search}%");
                    if (isset($config['code_key'])) {
                        $q->orWhere($config['code_key'], 'like', "%{$search}%");
                    }
                });
            }

            $items = $query->get()->map(function($item) use ($modName, $config) {
                $idKey = $config['id_key'] ?? 'id';
                $codeKey = $config['code_key'] ?? $idKey;
                $nameKey = $config['name_key'] ?? 'name';

                return [
                    'module' => $modName,
                    'record_id' => $item->{$idKey},
                    'code' => $item->{$codeKey} ?? $item->{$idKey},
                    'name' => $item->{$nameKey} ?? ('Record #' . $item->{$idKey}),
                    'deleted_at' => $item->deleted_at ? $item->deleted_at->toDateTimeString() : null,
                    'deleted_by_name' => $item->deletedBy ? $item->deletedBy->name : 'Sistem',
                ];
            });

            $allTrashItems = $allTrashItems->merge($items);
        }

        // Sort by deleted_at desc
        $sortedItems = $allTrashItems->sortByDesc('deleted_at')->values();

        // Manual Pagination
        $page = (int) $request->input('page', 1);
        $perPage = 15;
        $paginatedItems = new \Illuminate\Pagination\LengthAwarePaginator(
            $sortedItems->forPage($page, $perPage)->values(),
            $sortedItems->count(),
            $perPage,
            $page,
            ['path' => route('admin.trash.index'), 'query' => $request->query()]
        );

        $trashCounts = [];
        foreach ($moduleMap as $modName => $config) {
            $modelClass = $config['model'];
            $trashCounts[$modName] = $modelClass::onlyTrashed()->count();
        }

        return Inertia::render('Admin/Trash/Index', [
            'trashItems' => $paginatedItems,
            'trashCounts' => $trashCounts,
            'totalTrashCount' => $sortedItems->count(),
            'modules' => array_keys($moduleMap),
            'filters' => [
                'module' => $selectedModule,
                'search' => $search,
            ]
        ]);
    }

    /**
     * Restore Soft-Deleted Item with Unique Conflict Validation
     */
    public function restore(Request $request, $module, $id)
    {
        $moduleMap = $this->getModuleMap();
        if (!isset($moduleMap[$module])) {
            return back()->with('error', 'Modul tidak valid.');
        }

        $config = $moduleMap[$module];
        $modelClass = $config['model'];
        $idKey = $config['id_key'] ?? 'id';

        $record = $modelClass::onlyTrashed()->where($idKey, $id)->firstOrFail();

        // 1. Conflict Validation before Restore
        if (!empty($config['unique_fields'])) {
            foreach ($config['unique_fields'] as $field) {
                $val = $record->{$field};
                if ($val) {
                    $activeConflict = $modelClass::where($field, $val)->where($idKey, '!=', $id)->first();
                    if ($activeConflict) {
                        return back()->with('error', "Gagal Restore! Field '{$field}' dengan nilai '{$val}' sudah digunakan oleh data aktif lain. Silakan ubah data aktif terlebih dahulu.");
                    }
                }
            }
        }

        // 2. Perform Restore
        $record->restore();
        $record->update(['deleted_by' => null]);

        $nameKey = $config['name_key'] ?? 'name';
        $recordName = $record->{$nameKey} ?? ('Record #' . $id);

        app(AuditLogService::class)->log(
            'RESTORE',
            $module,
            ['deleted_at' => $record->deleted_at],
            [
                'record_id' => $id,
                'name' => $recordName,
                'status' => 'RESTORED'
            ]
        );

        return back()->with('success', "Data '{$recordName}' ({$module}) berhasil dipulihkan (Restore)!");
    }

    /**
     * Permanent Delete Item (Super Admin Only)
     */
    public function forceDelete(Request $request, $module, $id)
    {
        $user = auth()->user();
        if ($user->role !== 'admin' && !$user->hasRole('admin')) {
            return back()->with('error', 'Akses ditolak. Permanent Delete hanya untuk Admin.');
        }

        $moduleMap = $this->getModuleMap();
        if (!isset($moduleMap[$module])) {
            return back()->with('error', 'Modul tidak valid.');
        }

        $config = $moduleMap[$module];
        $modelClass = $config['model'];
        $idKey = $config['id_key'] ?? 'id';

        $record = $modelClass::onlyTrashed()->where($idKey, $id)->firstOrFail();

        $nameKey = $config['name_key'] ?? 'name';
        $recordName = $record->{$nameKey} ?? ('Record #' . $id);

        app(AuditLogService::class)->log(
            'PERMANENT_DELETE',
            $module,
            ['record_id' => $id, 'name' => $recordName],
            ['status' => 'PERMANENTLY_DELETED']
        );

        $record->forceDelete();

        return back()->with('success', "Data '{$recordName}' ({$module}) telah dihapus secara permanen dari database.");
    }
}
