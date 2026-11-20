<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Payment;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnlineOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCafe();
        Settings::set(['delivery_enabled' => true, 'delivery_fee' => 10000, 'delivery_min_order' => 30000]);
    }

    private function online(array $extra = [], int $qty = 2)
    {
        return $this->placePublicOrder([['menu_id' => $this->menu('French Fries')->id, 'qty' => $qty]], null, array_merge([
            'customer_name' => 'Rina', 'customer_phone' => '081211112222',
        ], $extra));
    }

    public function test_delivery_order_adds_fee_and_requires_address(): void
    {
        $this->online(['mode' => 'delivery'])->assertStatus(422); // tanpa alamat
        $res = $this->online(['mode' => 'delivery', 'delivery_address' => 'Jl. Mawar No. 12, RT 03, dekat masjid'])->assertCreated();
        $bill = Bill::where('tracking_key', $res->json('data.tracking_key'))->firstOrFail();
        $this->assertSame('delivery', $bill->type->value);
        $this->assertSame(10000, $bill->delivery_fee);
        // 44.000 + pajak 10% (4.400) + ongkir 10.000 (tidak kena pajak/service)
        $this->assertSame(58400, $bill->total);
        $this->assertNotNull($bill->queue_no);
    }

    public function test_delivery_minimum_order_and_toggle(): void
    {
        $this->online(['mode' => 'delivery', 'delivery_address' => 'Jl. Mawar No. 12, RT 03'], 1)
            ->assertStatus(422)->assertJsonPath('message', 'Minimal pemesanan Rp 30.000 untuk layanan ini.');
        Settings::set(['delivery_enabled' => false]);
        $this->online(['mode' => 'delivery', 'delivery_address' => 'Jl. Mawar No. 12, RT 03'])->assertStatus(422);
    }

    public function test_preorder_rules_and_ordering_while_closed(): void
    {
        Settings::set(['enforce_hours' => true, 'opening_hours' => array_fill_keys(array_map('strval', range(1, 7)), ['open' => '00:00', 'close' => '23:59', 'closed' => false])]);
        // terlalu dekat
        $this->online(['mode' => 'takeaway', 'scheduled_at' => now()->addMinutes(5)->toIso8601String()])->assertStatus(422);
        // terlalu jauh
        $this->online(['mode' => 'takeaway', 'scheduled_at' => now()->addDays(10)->toIso8601String()])->assertStatus(422);
        // valid
        $at = now()->addHours(2)->startOfHour();
        $res = $this->online(['mode' => 'takeaway', 'scheduled_at' => $at->toIso8601String()])->assertCreated();
        $this->assertSame($at->format('Y-m-d H:i'), Bill::where('tracking_key', $res->json('data.tracking_key'))->first()->scheduled_at->format('Y-m-d H:i'));

        // Toko tutup hari ini (jam operasional), tapi pre-order untuk besok tetap bisa
        $today = (string) now()->dayOfWeekIso;
        $hours = Settings::get('opening_hours');
        $hours[$today]['closed'] = true;
        Settings::set(['opening_hours' => $hours]);
        $this->online(['mode' => 'takeaway'])->assertStatus(423);
        $this->online(['mode' => 'takeaway', 'scheduled_at' => now()->addDay()->setTime(10, 0)->toIso8601String()])->assertCreated();
        // tapi jadwal di hari libur ditolak
        $this->online(['mode' => 'takeaway', 'scheduled_at' => now()->addMinutes(30)->toIso8601String()])->assertStatus(422);
    }

    public function test_pay_first_holds_order_until_gateway_payment(): void
    {
        config(['services.payment_gateway' => 'midtrans', 'services.midtrans.server_key' => 'SB-Mid-server-test']);
        Settings::set(['online_pay_first' => true, 'allow_online_payment' => true]);
        Http::fake(['*snap/v1/transactions' => Http::response(['token' => 'x', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/x'], 201)]);

        $res = $this->online(['mode' => 'takeaway'])->assertCreated()->assertJsonPath('data.requires_payment', true);
        $key = $res->json('data.tracking_key');
        $bill = Bill::where('tracking_key', $key)->first();

        // belum masuk dapur
        $this->actingAsRole('dapur');
        $this->getJson('/api/v1/kds?station=kitchen')->assertJsonCount(0, 'data.cards');

        // pelanggan bayar online -> webhook settlement
        $this->postJson("/api/v1/public/track/{$key}/pay")->assertOk()->assertJsonPath('data.url', 'https://app.sandbox.midtrans.com/snap/x');
        $payment = Payment::where('bill_id', $bill->id)->firstOrFail();
        $gross = $payment->amount.'.00';
        $this->postJson('/api/v1/public/webhooks/midtrans', [
            'order_id' => $payment->gateway_ref, 'status_code' => '200', 'gross_amount' => $gross,
            'signature_key' => hash('sha512', $payment->gateway_ref.'200'.$gross.'SB-Mid-server-test'),
            'transaction_status' => 'settlement',
        ])->assertOk();

        $this->assertSame('paid', $bill->fresh()->status->value);
        $this->actingAsRole('dapur');
        $this->getJson('/api/v1/kds?station=kitchen')->assertJsonCount(1, 'data.cards');
    }

    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        config(['services.payment_gateway' => 'midtrans', 'services.midtrans.server_key' => 'SB-Mid-server-test']);
        $this->postJson('/api/v1/public/webhooks/midtrans', ['order_id' => 'PAY-1', 'status_code' => '200', 'gross_amount' => '1.00', 'signature_key' => 'palsu', 'transaction_status' => 'settlement'])
            ->assertStatus(403);
    }

    public function test_cashier_can_take_payment_for_held_online_order(): void
    {
        config(['services.payment_gateway' => 'midtrans', 'services.midtrans.server_key' => 'SB-Mid-server-test']);
        Settings::set(['online_pay_first' => true]);
        $key = $this->online(['mode' => 'takeaway'])->json('data.tracking_key');
        $bill = Bill::where('tracking_key', $key)->first();

        $this->actingAsRole('kasir');
        $this->postJson('/api/v1/shifts/open', ['opening_cash' => 0])->assertCreated();
        $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'transfer', 'amount' => $bill->total]]])->assertOk();
        $this->assertSame('confirmed', $bill->orders()->first()->status->value);
    }

    public function test_staff_can_adjust_delivery_fee(): void
    {
        $key = $this->online(['mode' => 'delivery', 'delivery_address' => 'Jl. Mawar No. 12, RT 03'])->json('data.tracking_key');
        $bill = Bill::where('tracking_key', $key)->first();
        $this->actingAsRole('kasir');
        $this->postJson("/api/v1/bills/{$bill->id}/delivery", ['delivery_fee' => 15000])->assertOk()->assertJsonPath('data.delivery_fee', 15000);
        $this->assertSame(44000 + 4400 + 15000, $bill->fresh()->total);
    }
}
