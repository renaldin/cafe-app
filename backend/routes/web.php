<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name').' API',
        'status' => 'ok',
        'docs' => 'Gunakan endpoint /api/v1/...',
        'time' => now()->toIso8601String(),
    ]);
});
