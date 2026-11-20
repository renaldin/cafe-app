<?php

namespace App\Payments;

use App\Models\Bill;
use App\Models\Payment;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Xendit Invoice (QRIS, e-wallet, VA).
 * Webhook: POST /api/v1/public/webhooks/xendit — diverifikasi header x-callback-token.
 */
class XenditGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'xendit';
    }

    public function create(Bill $bill, Payment $payment, string $returnUrl): array
    {
        $ref = 'PAY-'.$payment->id.'-'.now()->format('His');
        $res = Http::withBasicAuth((string) config('services.xendit.secret_key'), '')
            ->acceptJson()->timeout(20)
            ->post('https://api.xendit.co/v2/invoices', [
                'external_id' => $ref,
                'amount' => (int) $payment->amount,
                'description' => Settings::get('cafe_name').' '.$bill->invoice_no,
                'invoice_duration' => 1800,
                'currency' => 'IDR',
                'success_redirect_url' => $returnUrl,
                'failure_redirect_url' => $returnUrl,
            ]);
        if (! $res->successful()) {
            throw new \App\Exceptions\BusinessException('Gagal membuat pembayaran online. Silakan bayar di kasir.', 502);
        }

        return ['ref' => $ref, 'url' => $res->json('invoice_url'), 'qr' => null, 'expires_at' => now()->addMinutes(30), 'gateway_id' => $res->json('id')];
    }

    public function parseWebhook(Request $request): ?array
    {
        $token = (string) config('services.xendit.callback_token');
        if (! $token || ! hash_equals($token, (string) $request->header('x-callback-token'))) {
            return null;
        }
        $ref = (string) $request->input('external_id');
        if (! $ref) {
            return null;
        }
        $status = match (strtoupper((string) $request->input('status'))) {
            'PAID', 'SETTLED' => 'paid',
            'EXPIRED' => 'expired',
            'PENDING' => 'pending',
            default => 'failed',
        };

        return ['ref' => $ref, 'status' => $status, 'payload' => $request->only(['id', 'external_id', 'status', 'paid_amount', 'payment_method', 'payment_channel'])];
    }

    public function checkStatus(Payment $payment): ?string
    {
        if (! $payment->gateway_ref) {
            return null;
        }
        $res = Http::withBasicAuth((string) config('services.xendit.secret_key'), '')->acceptJson()->timeout(10)
            ->get('https://api.xendit.co/v2/invoices', ['external_id' => $payment->gateway_ref]);
        if (! $res->successful()) {
            return null;
        }
        $inv = $res->json('0');
        if (! $inv) {
            return null;
        }

        return match (strtoupper((string) ($inv['status'] ?? ''))) {
            'PAID', 'SETTLED' => 'paid',
            'EXPIRED' => 'expired',
            'PENDING' => 'pending',
            default => 'failed',
        };
    }
}
