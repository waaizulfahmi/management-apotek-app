<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Permissions
        $permissions = [
            'user.manage',
            'medicine.view',
            'medicine.crud',
            'pos.checkout',
            'prescription.verify',
            'inventory.opname',
            'purchase.manage',
            'finance.view',
            'reports.view',
            'system.backup',
            'audit.view',
            'settings.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // 2. Create Roles & Assign Permissions
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->givePermissionTo(Permission::all());

        $adminApotek = Role::firstOrCreate(['name' => 'Admin Apotek']);
        $adminApotek->givePermissionTo([
            'medicine.view', 'medicine.crud', 'pos.checkout', 'prescription.verify',
            'inventory.opname', 'purchase.manage', 'finance.view', 'reports.view', 'settings.manage'
        ]);

        $apoteker = Role::firstOrCreate(['name' => 'Apoteker']);
        $apoteker->givePermissionTo([
            'medicine.view', 'medicine.crud', 'prescription.verify',
            'inventory.opname', 'reports.view'
        ]);

        $kasir = Role::firstOrCreate(['name' => 'Kasir']);
        $kasir->givePermissionTo(['medicine.view', 'pos.checkout']);

        $gudang = Role::firstOrCreate(['name' => 'Gudang']);
        $gudang->givePermissionTo(['medicine.view', 'inventory.opname', 'purchase.manage']);

        $manager = Role::firstOrCreate(['name' => 'Manager']);
        $manager->givePermissionTo([
            'medicine.view', 'purchase.manage', 'finance.view', 'reports.view'
        ]);

        // 3. Create Default Super Admin User
        $user = User::updateOrCreate(
            ['email' => 'admin@apotek.local'],
            [
                'name' => 'Super Administrator',
                'username' => 'superadmin',
                'password' => Hash::make('Admin123!'),
                'role' => 'admin',
                'no_hp' => '081234567890',
            ]
        );
        $user->assignRole($superAdmin);

        // 4. Create Sample Users for Other Roles
        $kasirUser = User::updateOrCreate(
            ['email' => 'kasir@apotek.local'],
            [
                'name' => 'Kasir Apotek',
                'username' => 'kasir1',
                'password' => Hash::make('Password123!'),
                'role' => 'kasir',
                'no_hp' => '081234567891',
            ]
        );
        $kasirUser->assignRole($kasir);

        $apotekerUser = User::updateOrCreate(
            ['email' => 'apoteker@apotek.local'],
            [
                'name' => 'Apoteker Utama',
                'username' => 'apoteker1',
                'password' => Hash::make('Password123!'),
                'role' => 'owner',
                'no_hp' => '081234567892',
            ]
        );
        $apotekerUser->assignRole($apoteker);
    }
}
