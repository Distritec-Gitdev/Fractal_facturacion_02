<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class CustomPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        $acciones = ['view_any','view','create','update','delete'];

        foreach ($acciones as $a) {
            Permission::findOrCreate($a . '_gestion_clientes', $guard);
        }

        foreach ($acciones as $a) {
            Permission::findOrCreate($a . '_clientes', $guard);
        }
    }
}
