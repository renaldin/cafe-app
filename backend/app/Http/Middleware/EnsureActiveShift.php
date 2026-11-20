<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/** Kasir tidak boleh bertransaksi tanpa shift aktif. */
class EnsureActiveShift
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || ! $user->openShift()) {
            return ApiResponse::error('Anda belum membuka shift. Buka shift terlebih dahulu untuk bertransaksi.', 409, ['shift' => ['required']]);
        }

        return $next($request);
    }
}
