<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Owner selalu punya akses penuh.
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('Owner') ? true : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('username')).'|'.$request->ip();

            return [
                Limit::perMinute(5)->by($key),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Catatan: pelanggan di WiFi cafe berbagi 1 IP publik (NAT), jadi limit per IP dibuat longgar
        // dan dikombinasikan dengan limit per path (per meja / per kode pelacakan).
        RateLimiter::for('public', function (Request $request) {
            return [
                Limit::perMinute(40)->by('p:'.$request->ip().'|'.$request->path()),
                Limit::perMinute(600)->by('g:'.$request->ip()),
            ];
        });

        RateLimiter::for('public-order', function (Request $request) {
            $token = (string) ($request->input('table_token') ?: 'takeaway');

            return [
                Limit::perMinute($token === 'takeaway' ? 20 : 5)->by('table:'.$token.'|'.($token === 'takeaway' ? $request->ip() : '')),
                Limit::perMinute(30)->by('ip:'.$request->ip()),
                Limit::perHour(300)->by('iph:'.$request->ip()),
            ];
        });

        RateLimiter::for('public-action', function (Request $request) {
            return Limit::perMinute(4)->by($request->ip().'|'.$request->path());
        });

        RateLimiter::for('staff', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?: $request->ip());
        });
    }
}
