<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission groups: [name => who gets it]. superadmin ("Owner") always
     * gets everything. Roles mirror the Flutter app's 5-role model:
     * Owner(superadmin) / Admin / Manager / Inventory Manager / Cashier.
     */
    public const PERMISSIONS = [
        'dashboard.view' => ['admin', 'manager', 'inventory_manager', 'cashier'],
        'products.view' => ['admin', 'manager', 'inventory_manager', 'cashier'],
        'products.manage' => ['admin', 'manager', 'inventory_manager'],
        'inventory.view' => ['admin', 'manager', 'inventory_manager', 'cashier'],
        'inventory.adjust' => ['admin', 'manager', 'inventory_manager'],
        'pos.use' => ['cashier', 'inventory_manager', 'manager'],
        'pos.view' => ['admin', 'manager', 'inventory_manager', 'cashier'],
        'refunds.view' => ['admin', 'manager', 'cashier'],
        'refunds.request' => ['cashier'],
        'refunds.approve' => ['admin', 'manager'],
        'exchanges.process' => ['admin', 'manager', 'cashier'],
        'imports.run' => ['admin', 'manager'],
        'imports.view' => ['admin', 'manager', 'inventory_manager'],
        'reports.view' => ['admin', 'manager'],
        'audit.view' => ['admin', 'superadmin'],
        'users.manage' => [], // superadmin only
        'settings.manage' => [], // superadmin only
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (['superadmin', 'admin', 'manager', 'inventory_manager', 'cashier'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $roleInstances = [
            'superadmin' => Role::findByName('superadmin', 'web'),
            'admin' => Role::findByName('admin', 'web'),
            'manager' => Role::findByName('manager', 'web'),
            'inventory_manager' => Role::findByName('inventory_manager', 'web'),
            'cashier' => Role::findByName('cashier', 'web'),
        ];

        foreach (self::PERMISSIONS as $name => $roles) {
            foreach ($roles as $roleName) {
                $roleInstances[$roleName]->givePermissionTo($name);
            }
        }

        // superadmin (Owner) guards the door itself: everything.
        $roleInstances['superadmin']->givePermissionTo(Permission::all());
    }
}
