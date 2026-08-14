<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Default Outlets
        $outletPwt = Outlet::firstOrCreate(
            ['code' => 'PWT'],
            ['name' => 'Apotek Medika Purwokerto', 'address' => 'Jl. Jenderal Soedirman No. 45 Purwokerto', 'phone' => '0281-635412', 'is_active' => true]
        );

        $outletPbg = Outlet::firstOrCreate(
            ['code' => 'PBG'],
            ['name' => 'Apotek Medika Purbalingga', 'address' => 'Jl. Ahmad Yani No. 12 Purbalingga', 'phone' => '0281-891234', 'is_active' => true]
        );

        // 2. Create Matrix Permissions (14 Modules x 6 Actions)
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
            'retur' => 'Retur Barang',
            'shift' => 'Manajemen Shift Kasir',
            'sales' => 'Laporan Penjualan',
        ];

        $actions = ['view', 'create', 'edit', 'delete', 'approve', 'export', 'refund', 'cancel', 'open', 'close', 'force_close', 'adjust_cash', 'view_all_cashier', 'view_cashier'];

        foreach ($modules as $modKey => $modLabel) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$modKey}.{$action}"]);
            }
        }

        // 3. Create Default Roles
        $roles = [
            'Super Admin' => Permission::all()->pluck('name')->toArray(),
            'Owner' => Permission::all()->pluck('name')->toArray(),
            'Apoteker' => [
                'dashboard.view', 'products.view', 'products.edit', 'stock.view', 'opname.view', 'opname.create', 'opname.approve',
                'stock_card.view', 'pos.view', 'pos.create', 'po.view', 'membership.view', 'reports.view', 'retur.view', 'retur.create', 'retur.approve'
            ],
            'Kasir' => [
                'dashboard.view', 'products.view', 'stock.view', 'pos.view', 'pos.create', 'membership.view', 'membership.create'
            ],
            'Gudang' => [
                'dashboard.view', 'products.view', 'stock.view', 'stock.edit', 'opname.view', 'opname.create',
                'stock_card.view', 'po.view', 'suppliers.view', 'retur.view', 'retur.create'
            ],
            'Purchasing' => [
                'dashboard.view', 'products.view', 'stock.view', 'po.view', 'po.create', 'po.edit', 'po.approve', 'suppliers.view', 'suppliers.create', 'retur.view', 'retur.create'
            ],
            'Keuangan' => [
                'dashboard.view', 'pos.view', 'po.view', 'finance.view', 'finance.create', 'finance.edit', 'finance.approve', 'reports.view', 'reports.export', 'retur.view', 'retur.refund'
            ],
        ];

        foreach ($roles as $roleName => $perms) {
            $roleObj = Role::firstOrCreate(['name' => $roleName]);
            $roleObj->syncPermissions($perms);
        }

        // 4. Create / Update Standard Users
        $adminRole = Role::findByName('Super Admin');
        $kasirRole = Role::findByName('Kasir');
        $apotekerRole = Role::findByName('Apoteker');

        $adminUser = User::where('email', 'admin@gmail.com')->orWhere('username', 'budi')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'name' => 'Budi Prasetyo (Admin)',
                'username' => 'budi',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'ACTIVE',
                'outlet_id' => $outletPwt->id,
                'no_hp' => '081234567890',
            ]);
        } else {
            $adminUser->update(['status' => 'ACTIVE', 'outlet_id' => $outletPwt->id]);
        }
        $adminUser->assignRole($adminRole);
        $adminUser->outlets()->sync([$outletPwt->id, $outletPbg->id]);

        $kasirUser = User::where('email', 'kasir@gmail.com')->orWhere('username', 'kasir')->first();
        if (!$kasirUser) {
            $kasirUser = User::create([
                'name' => 'Siti Aminah (Kasir)',
                'username' => 'kasir',
                'email' => 'kasir@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'kasir',
                'status' => 'ACTIVE',
                'outlet_id' => $outletPwt->id,
                'no_hp' => '081234567891',
            ]);
        } else {
            $kasirUser->update(['status' => 'ACTIVE', 'outlet_id' => $outletPwt->id]);
        }
        $kasirUser->assignRole($kasirRole);
        $kasirUser->outlets()->sync([$outletPwt->id]);

        $apotekerUser = User::where('email', 'apoteker@apotek.local')->orWhere('username', 'hendra')->first();
        if (!$apotekerUser) {
            $apotekerUser = User::create([
                'name' => 'Dr. Apt. Hendra Gunawan',
                'username' => 'hendra',
                'email' => 'apoteker@apotek.local',
                'password' => Hash::make('Password123!'),
                'role' => 'owner',
                'status' => 'ACTIVE',
                'outlet_id' => $outletPwt->id,
                'no_hp' => '081234567892',
            ]);
        } else {
            $apotekerUser->update(['status' => 'ACTIVE', 'outlet_id' => $outletPwt->id]);
        }
        $apotekerUser->assignRole($apotekerRole);
        $apotekerUser->outlets()->sync([$outletPwt->id, $outletPbg->id]);
    }
}
