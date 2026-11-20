<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->after('opened_at'); // pre-order: jam ambil/antar
            $table->index('scheduled_at');
        });
        Schema::table('orders', function (Blueprint $table) {
            // pesanan online yang menunggu pembayaran sebelum masuk dapur
            $table->boolean('requires_payment')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex(['scheduled_at']);
            $table->dropColumn('scheduled_at');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('requires_payment');
        });
    }
};
