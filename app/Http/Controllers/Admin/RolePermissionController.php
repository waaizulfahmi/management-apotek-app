<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Display roles with their permission matrix
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();

        $modules = [
            'dashboard' => 'Dashboard',
            'users' => 'User & Access',
            'products' => 'Produk',
            'stock' => 'Stok',
            'opname' => 'Stok Opname',
            'stock_card' => 'Kartu Stok',
            'pos' => 'Penjualan / POS',
            'po' => 'Pembelian / PO',
            'suppliers' => 'Supplier / PBF',
            'membership' => 'Membership',
            'finance' => 'Keuangan',
            'reports' => 'Laporan',
            'settings' => 'Pengaturan',
        ];

        $actions = ['view', 'create', 'edit', 'delete', 'approve', 'export'];

        $allPermissions = Permission::all();

        return Inertia::render('Admin/Users/Roles/Index', [
            'roles' => $roles,
            'modules' => $modules,
            'actions' => $actions,
            'allPermissions' => $allPermissions,
        ]);
    }

    /**
     * Create a new role
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
        ]);

        $role = Role::create(['name' => $request->name]);

        $this->auditLogService->log(
            'CREATE_ROLE',
            'User & Access',
            null,
            ['role_id' => $role->id, 'name' => $role->name]
        );

        return redirect()->back()->with('success', "Role '{$role->name}' berhasil ditambahkan!");
    }

    /**
     * Update role permissions (matrix checkbox)
     */
    public function updatePermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'permissions' => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $oldPermissions = $role->permissions->pluck('name')->toArray();

        $role->syncPermissions($request->permissions);

        $this->auditLogService->log(
            'UPDATE_PERMISSIONS',
            'User & Access',
            ['permissions' => $oldPermissions],
            ['permissions' => $request->permissions, 'role' => $role->name]
        );

        return redirect()->back()->with('success', "Permissions untuk role '{$role->name}' berhasil diperbarui!");
    }

    /**
     * Delete a custom role (protect default roles)
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        $protected = ['Super Admin', 'Owner', 'Apoteker', 'Kasir', 'Gudang', 'Purchasing', 'Keuangan'];
        if (in_array($role->name, $protected)) {
            return redirect()->back()->withErrors(['error' => "Role default '{$role->name}' tidak dapat dihapus!"]);
        }

        $this->auditLogService->log(
            'DELETE_ROLE',
            'User & Access',
            ['role_id' => $role->id, 'name' => $role->name],
            null
        );

        $role->delete();

        return redirect()->back()->with('success', "Role '{$role->name}' berhasil dihapus!");
    }
}
