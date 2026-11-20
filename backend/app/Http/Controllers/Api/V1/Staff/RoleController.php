<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleRequest;
use App\Support\Activity;
use App\Support\Permissions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::query()->with('permissions:id,name')->orderBy('id')->get();
        $counts = DB::table('model_has_roles')->selectRaw('role_id, COUNT(*) as c')->groupBy('role_id')->pluck('c', 'role_id');

        return $this->ok($roles->map(fn ($r) => [
            'id' => $r->id, 'name' => $r->name, 'users_count' => (int) ($counts[$r->id] ?? 0),
            'is_system' => $r->name === 'Owner',
            'permissions' => $r->name === 'Owner' ? array_keys(Permissions::flat()) : $r->permissions->pluck('name'),
        ]));
    }

    public function permissions()
    {
        return $this->ok(Permissions::grouped());
    }

    public function store(RoleRequest $request)
    {
        $data = $request->validated();
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Activity::log('role.create', $role, "Tambah role {$role->name}", null, ['permissions' => $data['permissions'] ?? []]);

        return $this->created(['id' => $role->id, 'name' => $role->name]);
    }

    public function update(RoleRequest $request, Role $role)
    {
        if ($role->name === 'Owner') {
            throw new BusinessException('Role Owner tidak dapat diubah.');
        }
        $data = $request->validated();
        $before = $role->permissions->pluck('name');
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Activity::log('role.update', $role, "Ubah role {$role->name}", ['permissions' => $before], ['permissions' => $data['permissions'] ?? []]);

        return $this->ok(['id' => $role->id, 'name' => $role->name], 'Role diperbarui.');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, ['Owner', 'Manajer', 'Kasir', 'Pelayan', 'Dapur', 'Bar'], true)) {
            throw new BusinessException('Role bawaan tidak dapat dihapus.');
        }
        if (DB::table('model_has_roles')->where('role_id', $role->id)->exists()) {
            throw new BusinessException('Role masih dipakai oleh staf.');
        }
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->ok(null, 'Role dihapus.');
    }
}
