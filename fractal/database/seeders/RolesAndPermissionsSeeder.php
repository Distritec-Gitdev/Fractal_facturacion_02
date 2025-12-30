<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app('cache')->forget('spatie.permission.cache');

        $perms = [
            'orders.view', 'orders.create', 'orders.update', 'orders.delete',
            'users.view',  'users.create',  'users.update',  'users.delete',
            'reports.view',
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $admin   = Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $user    = Role::firstOrCreate(['name' => 'user',    'guard_name' => 'web']);

        $admin->syncPermissions($perms);
        $manager->syncPermissions(['orders.view','orders.update','reports.view']);
        $user->syncPermissions(['orders.view']);
    }
}
