<?php

namespace App\Payments;

use App\Models\Bill;
use App\Models\Payment;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Midtrans Snap (QRIS, GoPay, ShopeePay, VA, dll).
 * Webhook: POST /api/v1/public/webhooks/midtrans
 * Signature: sha512(order_id + status_code + gross_amount + server_key)
 */
class MidtransGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'midtrans';
    }

    private function serverKey(): string
    {
        return (string) config('services.midtrans.server_key');
    }

    private function isProduction(): bool
    {
        return (bool) config('services.midtrans.production');
    }

    private function snapUrl(): string
    {
        return $this->isProduction() ? 'https://app.midtrans.com/snap/v1/transactions' : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    private function apiUrl(): string
    {
        return $this->isProduction() ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
    }

    public function create(Bill $bill, Payment $payment, string $returnUrl): array
    {
        $orderId = 'PAY-'.$payment->id.'-'.now()->format('His');
        $res = Http::withBasicAuth($this->serverKey(), '')
            ->acceptJson()->timeout(20)
            ->post($this->snapUrl(), [
                'transaction_details' => ['order_id' => $orderId, 'gross_amount' => (int) $payment->amount],
                'customer_details' => array_filter(['first_name' => $bill->customer_name ?: 'Pelanggan', 'phone' => $bill->customer_phone]),
                'item_details' => [[
                    'id' => $bill->invoice_no, 'price' => (int) $payment->amount, 'quantity' => 1,
                    'name' => mb_substr(Settings::get('cafe_name').' '.$bill->invoice_no, 0, 50),
                ]],
                'callbacks' => ['finish' => $returnUrl],
                'expiry' => ['unit' => 'minutes', 'duration' => 30],
            ]);
        if (! $res->successful()) {
            throw new \App\Exceptions\BusinessException('Gagal membuat pembayaran online. Silakan bayar di kasir.', 502);
        }

        return ['ref' => $orderId, 'url' => $res->json('redirect_url'), 'qr' => null, 'expires_at' => now()->addMinutes(30)];
    }

    public function parseWebhook(Request $request): ?array
    {
        $orderId = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $gross = (string) $request->input('gross_amount');
        $signature = (string) $request->input('signature_key');
        $expected = hash('sha512', $orderId.$statusCode.$gross.$this->serverKey());
        if (! $orderId || ! hash_equals($expected, $signature)) {
            return null;
        }

        return ['ref' => $orderId, 'status' => $this->mapStatus((string) $request->input('transaction_status'), (string) $request->input('fraud_status')), 'payload' => $request->only(['order_id', 'transaction_status', 'payment_type', 'gross_amount', 'transaction_time'])];
    }

    public function checkStatus(Payment $payment): ?string
    {
        if (! $payment->gateway_ref) {
            return null;
        }
        $res = Http::withBasicAuth($this->serverKey(), '')->acceptJson()->timeout(10)
            ->get($this->apiUrl().'/v2/'.urlencode($payment->gateway_ref).'/status');
        if (! $res->successful() || ! $res->json('transaction_status')) {
            return null;
        }

        return $this->mapStatus((string) $res->json('transaction_status'), (string) $res->json('fraud_status'));
    }

    private function mapStatus(string $status, string $fraud): string
    {
        return match ($status) {
            'capture' => $fraud === 'challenge' ? 'pending' : 'paid',
            'settlement' => 'paid',
            'pending' => 'pending',
            'expire' => 'expired',
            default => 'failed', // deny, cancel, failure
        };
    }
}
