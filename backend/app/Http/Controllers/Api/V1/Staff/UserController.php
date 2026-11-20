<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->with('roles')
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('username', 'like', "%$s%")))
            ->orderBy('name');

        return $this->paginated($q->paginate($this->perPage()), UserResource::class);
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();
        $this->guardOwnerRole($request, $data['role']);
        $user = User::create(collect($data)->except('role')->all());
        $user->syncRoles([$data['role']]);
        Activity::log('user.create', $user, "Tambah staf {$user->name} ({$data['role']})");

        return $this->created(UserResource::make($user->load('roles')));
    }

    public function update(UserRequest $request, User $user)
    {
        $data = $request->validated();
        $this->guardOwnerRole($request, $data['role'], $user);
        if ($user->id === $request->user()->id && empty($data['is_active'] ?? true)) {
            throw new BusinessException('Anda tidak dapat menonaktifkan akun sendiri.');
        }
        $before = ['role' => $user->getRoleNames()->first(), 'is_active' => $user->is_active];
        $payload = collect($data)->except(['role', 'password'])->all();
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }
        $user->update($payload);
        $user->syncRoles([$data['role']]);
        if (isset($data['is_active']) && ! $data['is_active']) {
            $user->tokens()->delete();
        }
        Activity::log('user.update', $user, "Ubah staf {$user->name}", $before, ['role' => $data['role'], 'is_active' => $user->is_active]);

        return $this->ok(UserResource::make($user->load('roles')), 'Staf diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            throw new BusinessException('Anda tidak dapat menghapus akun sendiri.');
        }
        if ($user->isOwner() && User::role('Owner')->count() <= 1) {
            throw new BusinessException('Minimal harus ada satu Owner.');
        }
        $this->guardOwnerRole($request, $user->getRoleNames()->first() ?? '', $user);
        $user->tokens()->delete();
        $user->update(['username' => $user->username.'_del'.$user->id, 'email' => null]);
        $user->delete();
        Activity::log('user.delete', $user, "Hapus staf {$user->name}");

        return $this->ok(null, 'Staf dihapus.');
    }

    /** Hanya Owner yang boleh membuat/mengubah akun Owner. */
    private function guardOwnerRole(Request $request, string $role, ?User $target = null): void
    {
        if (! $request->user()->isOwner() && ($role === 'Owner' || $target?->isOwner())) {
            throw new BusinessException('Hanya Owner yang dapat mengelola akun Owner.', 403);
        }
    }
}
