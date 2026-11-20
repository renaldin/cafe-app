<?php
/**
 * =====================================================================
 *  INSTALLER DARURAT TANPA SSH (cPanel tanpa Terminal)
 * =====================================================================
 *  1. Isi SETUP_TOKEN=<string acak panjang> di file .env
 *  2. Salin file ini ke public/setup.php
 *  3. Buka: https://api.domainanda.com/setup.php?token=<SETUP_TOKEN>&action=migrate
 *     Aksi: migrate | seed | optimize | clear
 *  4. WAJIB HAPUS public/setup.php dan kosongkan SETUP_TOKEN setelah selesai!
 * =====================================================================
 */

use Illuminate\Contracts\Console\Kernel;

define('LARAVEL_START', microtime(true));
$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require_once $base.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');
$token = (string) env('SETUP_TOKEN', '');
if (strlen($token) < 32 || ! hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit("Akses ditolak. Set SETUP_TOKEN (min 32 karakter) di .env.\n");
}

$action = $_GET['action'] ?? '';
$commands = [
    'migrate' => ['migrate', ['--force' => true]],
    'seed' => ['db:seed', ['--force' => true]],
    'optimize' => ['optimize', []],
    'clear' => ['optimize:clear', []],
];
if (! isset($commands[$action])) {
    exit("Aksi tersedia: migrate, seed, optimize, clear\n");
}
[$cmd, $args] = $commands[$action];
$status = $kernel->call($cmd, $args);
echo "> php artisan $cmd\n\n".$kernel->output()."\nExit code: $status\n\n";
echo "INGAT: hapus file setup.php ini setelah selesai!\n";
