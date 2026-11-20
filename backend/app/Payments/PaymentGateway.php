<?php

namespace App\Payments;

use App\Models\Bill;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): string;

    /** Buat transaksi; kembalikan ['ref' => ..., 'url' => ..., 'qr' => ..., 'expires_at' => ...] */
    public function create(Bill $bill, Payment $payment, string $returnUrl): array;

    /** Verifikasi webhook. Return ['ref' => ..., 'status' => paid|pending|failed|expired, 'payload' => []] atau null bila tidak valid */
    public function parseWebhook(Request $request): ?array;

    /** Cek status langsung ke gateway (fallback polling). */
    public function checkStatus(Payment $payment): ?string;
}
