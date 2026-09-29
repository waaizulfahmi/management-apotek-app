<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    protected $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * User Management Dashboard Metrics
     */
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'ACTIVE')->count(),
            'inactive_users' => User::where('status', 'INACTIVE')->count(),
            'suspended_users' => User::where('status', 'SUSPENDED')->count(),
            'total_roles' => Role::count(),
            'today_logins' => User::whereDate('last_login_at', now()->toDateString())->count(),
        ];

        $recentLogins = User::with(['primaryOutlet', 'roles'])
            ->whereNotNull('last_login_at')
            ->orderBy('last_login_at', 'desc')
            ->limit(5)
            ->get();

        $roleDistribution = Role::withCount('users')->get();

        return Inertia::render('Admin/Users/Dashboard', [
            'stats' => $stats,
            'recentLogins' => $recentLogins,
            'roleDistribution' => $roleDistribution,
        ]);
    }

    /**
     * User List Page with Search, Filter Role, Status, Outlet & Pagination
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'primaryOutlet', 'outlets']);

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('no_hp', 'like', "%{$search}%");
            });
        }

        if ($request->role) {
            $role = $request->role;
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->outlet_id) {
            $outletId = $request->outlet_id;
            $query->where(function ($q) use ($outletId) {
                $q->where('outlet_id', $outletId)
                  ->orWhereHas('outlets', function ($q2) use ($outletId) {
                      $q2->where('outlets.id', $outletId);
                  });
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate(10)->withQueryString();

        $roles = Role::pluck('name');
        $outlets = Outlet::where('is_active', true)->get(['id', 'code', 'name']);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'outlets' => $outlets,
            'filters' => $request->only(['search', 'role', 'status', 'outlet_id']),
        ]);
    }

    /**
     * Store New User
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'no_hp' => 'nullable|string|max:30',
            'password' => 'required|string|min:6',
            'role' => 'required|string|exists:roles,name',
            'outlet_id' => 'nullable|exists:outlets,id',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'exists:outlets,id',
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'username' => strtolower($request->username),
                'email' => strtolower($request->email),
                'no_hp' => $request->no_hp,
                'password' => Hash::make($request->password),
                'role' => strtolower($request->role),
                'status' => 'ACTIVE',
                'outlet_id' => $request->outlet_id,
            ]);

            $roleObj = Role::findByName($request->role);
            $user->assignRole($roleObj);

            if ($request->outlet_ids) {
                $user->outlets()->sync($request->outlet_ids);
            } else if ($request->outlet_id) {
                $user->outlets()->sync([$request->outlet_id]);
            }

            $this->auditLogService->log(
                'CREATE_USER',
                'User & Access',
                null,
                ['user_id' => $user->id, 'name' => $user->name, 'role' => $request->role]
            );
        });

        return redirect()->back()->with('success', 'User berhasil ditambahkan!');
    }

    /**
     * User Detail Profile Page (4 Tabs: Profile, Role & Permission, Outlet Access, Audit Log)
     */
    public function show($id)
    {
        $user = User::with(['roles.permissions', 'primaryOutlet', 'outlets', 'auditLogs'])->findOrFail($id);
        $allPermissions = $user->getAllPermissions();
        $outlets = Outlet::all();

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
            'permissions' => $allPermissions,
            'outlets' => $outlets,
        ]);
    }

    /**
     * Update User Info & Role
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username,' . $id,
            'email' => 'required|email|max:255|unique:users,email,' . $id,
            'no_hp' => 'nullable|string|max:30',
            'role' => 'required|string|exists:roles,name',
            'status' => 'required|in:ACTIVE,INACTIVE,SUSPENDED',
            'outlet_id' => 'nullable|exists:outlets,id',
            'outlet_ids' => 'nullable|array',
        ]);

        $before = $user->only(['name', 'username', 'email', 'status', 'outlet_id']);

        DB::transaction(function () use ($request, $user, $before) {
            $user->update([
                'name' => $request->name,
                'username' => strtolower($request->username),
                'email' => strtolower($request->email),
                'no_hp' => $request->no_hp,
                'status' => $request->status,
                'outlet_id' => $request->outlet_id,
            ]);

            $user->syncRoles([$request->role]);

            if ($request->has('outlet_ids')) {
                $user->outlets()->sync($request->outlet_ids);
            }

            $this->auditLogService->log(
                'EDIT_USER',
                'User & Access',
                $before,
                $user->only(['name', 'username', 'email', 'status', 'outlet_id'])
            );
        });

        return redirect()->back()->with('success', "User {$user->name} berhasil diperbarui!");
    }

    /**
     * Reset Password
     */
    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make($request->password),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $this->auditLogService->log(
            'RESET_PASSWORD',
            'User & Access',
            null,
            ['target_user' => $user->name]
        );

        return redirect()->back()->with('success', "Password user {$user->name} berhasil di-reset!");
    }

    /**
     * Toggle User Status (ACTIVE / INACTIVE / SUSPENDED)
     */
    public function toggleStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:ACTIVE,INACTIVE,SUSPENDED',
        ]);

        $user = User::findOrFail($id);
        $oldStatus = $user->status;

        $user->update([
            'status' => $request->status,
        ]);

        $this->auditLogService->log(
            'TOGGLE_STATUS',
            'User & Access',
            ['status' => $oldStatus],
            ['status' => $request->status]
        );

        return redirect()->back()->with('success', "Status user {$user->name} diubah menjadi {$request->status}!");
    }

    /**
     * Force Logout User
     */
    public function forceLogout($id)
    {
        $user = User::findOrFail($id);
        $user->update([
            'remember_token' => null,
        ]);

        $this->auditLogService->log(
            'FORCE_LOGOUT',
            'User & Access',
            null,
            ['target_user' => $user->name]
        );

        return redirect()->back()->with('success', "User {$user->name} berhasil di-force logout!");
    }

    /**
     * Manage & Update User Outlet Access
     */
    public function updateOutlets(Request $request, $id)
    {
        $targetUser = User::findOrFail($id);

        $request->validate([
            'access_all_outlets' => 'boolean',
            'outlet_ids' => 'required_if:access_all_outlets,false|array',
            'outlet_ids.*' => 'exists:outlets,id',
            'primary_outlet_id' => 'required|exists:outlets,id',
        ]);

        $accessAll = (bool) $request->access_all_outlets;
        $primaryOutletId = (int) $request->primary_outlet_id;
        $requestedOutletIds = $accessAll ? [] : array_map('intval', $request->outlet_ids ?: []);

        if (!$accessAll && !in_array($primaryOutletId, $requestedOutletIds)) {
            $requestedOutletIds[] = $primaryOutletId;
        }

        // Active shift safeguard: ensure target user does not have an open active shift in an outlet being revoked
        $existingAssignedIds = DB::table('user_outlets')
            ->where('user_id', $targetUser->id)
            ->whereNull('deleted_at')
            ->pluck('outlet_id')
            ->toArray();

        $revokedIds = array_diff($existingAssignedIds, $requestedOutletIds);
        if (!$accessAll && !empty($revokedIds)) {
            $hasActiveShift = DB::table('cashier_shifts')
                ->where(function ($q) use ($targetUser) {
                    $q->where('cashier_id', $targetUser->id);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('cashier_shifts', 'user_id')) {
                        $q->orWhere('user_id', $targetUser->id);
                    }
                })
                ->where('status', 'OPEN')
                ->whereIn('outlet_id', $revokedIds)
                ->exists();

            if ($hasActiveShift) {
                return redirect()->back()->with('error', 'User masih memiliki shift aktif pada outlet yang akan dicabut aksesnya. Tutup shift terlebih dahulu sebelum menghapus akses outlet!');
            }
        }

        DB::beginTransaction();
        try {
            // Update users table flags & primary outlet_id
            $targetUser->update([
                'access_all_outlets' => $accessAll,
                'outlet_id' => $primaryOutletId,
            ]);

            if ($accessAll) {
                // Soft delete specific mapping since user accesses all outlets
                DB::table('user_outlets')
                    ->where('user_id', $targetUser->id)
                    ->update(['deleted_at' => now()]);

                // Ensure primary outlet record exists
                DB::table('user_outlets')->updateOrInsert(
                    ['user_id' => $targetUser->id, 'outlet_id' => $primaryOutletId],
                    ['is_primary' => true, 'deleted_at' => null, 'updated_at' => now()]
                );

                $this->auditLogService->log(
                    'GRANT_ALL_OUTLETS',
                    'User & Access',
                    null,
                    ['user_id' => $targetUser->id, 'target_user' => $targetUser->name, 'primary_outlet' => $primaryOutletId]
                );
            } else {
                // Revoke removed outlets via soft delete
                DB::table('user_outlets')
                    ->where('user_id', $targetUser->id)
                    ->whereNotIn('outlet_id', $requestedOutletIds)
                    ->update(['deleted_at' => now()]);

                // Sync requested outlets
                foreach ($requestedOutletIds as $outId) {
                    $isPrimary = ($outId === $primaryOutletId);
                    $existing = DB::table('user_outlets')
                        ->where('user_id', $targetUser->id)
                        ->where('outlet_id', $outId)
                        ->first();

                    if ($existing) {
                        DB::table('user_outlets')
                            ->where('id', $existing->id)
                            ->update([
                                'is_primary' => $isPrimary,
                                'deleted_at' => null,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('user_outlets')->insert([
                            'user_id' => $targetUser->id,
                            'outlet_id' => $outId,
                            'is_primary' => $isPrimary,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $this->auditLogService->log(
                    'ASSIGN_OUTLET',
                    'User & Access',
                    null,
                    ['user_id' => $targetUser->id, 'target_user' => $targetUser->name, 'assigned_outlets' => $requestedOutletIds, 'primary_outlet' => $primaryOutletId]
                );
            }

            DB::commit();
            return redirect()->back()->with('success', "Akses outlet untuk user {$targetUser->name} berhasil diperbarui!");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
