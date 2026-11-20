<?php

use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

/*
 * Dijalankan oleh cron cPanel setiap menit:
 *   * * * * * /usr/local/bin/php /home/USER/api/artisan schedule:run >> /dev/null 2>&1
 */

// Bersihkan token kedaluwarsa
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Tandai pembayaran online yang kedaluwarsa
Schedule::call(function () {
    Payment::where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<', now())->update(['status' => 'expired']);
})->everyTenMinutes()->name('expire-payments')->withoutOverlapping();

// Bersihkan cache & session kedaluwarsa (driver database)
Schedule::call(function () {
    DB::table('cache')->where('expiration', '<', now()->timestamp)->delete();
    DB::table('sessions')->where('last_activity', '<', now()->subDays(2)->timestamp)->delete();
})->daily()->name('cleanup-cache');

// Arsip log aktivitas > 1 tahun
Schedule::call(fn () => DB::table('activity_logs')->where('created_at', '<', now()->subYear())->delete())->monthly()->name('prune-logs');
