<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $user = User::query()->where('username', $data['username'])->orWhere('email', $data['username'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Activity::log('auth.login_failed', $user, 'Login gagal: '.$data['username'], userId: $user?->id);
            throw ValidationException::withMessages(['username' => 'Username atau password salah.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['username' => 'Akun Anda dinonaktifkan.']);
        }
        $token = $user->createToken($data['device_name'] ?? 'web', ['*'], now()->addDays(14))->plainTextToken;
        $user->forceFill(['last_login_at' => now()])->save();
        Activity::log('auth.login', $user, 'Login', userId: $user->id);

        return $this->ok(['token' => $token] + $this->profile($user), 'Berhasil masuk.');
    }

    public function me(Request $request)
    {
        return $this->ok($this->profile($request->user()));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->ok(null, 'Berhasil keluar.');
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $user = $request->user();
        $user->update(['password' => $data['password']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        Activity::log('auth.password', $user, 'Ganti password');

        return $this->ok(null, 'Password berhasil diganti.');
    }

    private function profile(User $user): array
    {
        $user->load('roles');
        $perms = $user->isOwner()
            ? \Spatie\Permission\Models\Permission::pluck('name')
            : $user->getAllPermissions()->pluck('name');
        $shift = $user->openShift();

        return [
            'user' => UserResource::make($user)->resolve(),
            'permissions' => $perms->values(),
            'shift' => $shift ? ['id' => $shift->id, 'opened_at' => $shift->opened_at->toIso8601String(), 'opening_cash' => $shift->opening_cash] : null,
        ];
    }
}
