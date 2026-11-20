<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->is_active) {
            $user->currentAccessToken()?->delete();

            return ApiResponse::error('Akun Anda dinonaktifkan. Hubungi pemilik/manajer.', 401);
        }

        return $next($request);
    }
}
