<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::firstOrCreate(
            ['email' => 'admin@zionpos.test'],
            ['name' => 'Super Admin', 'username' => 'admin', 'password' => 'password'],
        );
        $superadmin->assignRole('superadmin');

        // Day-to-day staff accounts for local testing.
        $admin = User::firstOrCreate(
            ['email' => 'manager@zionpos.test'],
            ['name' => 'Zion Owner', 'username' => 'owner', 'password' => 'password'],
        );
        $admin->assignRole('admin');

        $cashier = User::firstOrCreate(
            ['email' => 'cashier@zionpos.test'],
            ['name' => 'Till Operator', 'username' => 'cashier', 'password' => 'password'],
        );
        $cashier->assignRole('cashier');
    }
}
