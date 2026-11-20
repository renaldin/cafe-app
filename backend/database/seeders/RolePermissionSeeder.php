<?php

namespace Database\Seeders;

use App\Models\Station;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_keys(Permissions::flat()) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (Permissions::defaultRoles() as $role => $perms) {
            $r = Role::findOrCreate($role, 'web');
            if ($role !== 'Owner' && $r->permissions()->count() === 0) {
                $r->syncPermissions($perms);
            }
            if ($role === 'Owner') {
                $r->syncPermissions($perms);
            }
        }
        Station::firstOrCreate(['code' => 'kitchen'], ['name' => 'Dapur']);
        Station::firstOrCreate(['code' => 'bar'], ['name' => 'Bar']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
