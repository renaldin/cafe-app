<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCafe();
    }

    public function test_customer_can_view_menu_and_store(): void
    {
        $this->getJson('/api/v1/public/store')->assertOk()->assertJsonPath('data.is_open', true);
        $res = $this->getJson('/api/v1/public/menu')->assertOk();
        $this->assertNotEmpty($res->json('data.0.menus'));
        $this->assertArrayNotHasKey('cost', $res->json('data.0.menus.0'));
    }

    public function test_order_total_is_calculated_on_server_and_client_price_ignored(): void
    {
        $menu = $this->menu('Cappuccino'); // 28.000
        $opts = $this->defaultOptions($menu, ['Ukuran' => 'Large']); // +5.000
        $res = $this->placePublicOrder([[
            'menu_id' => $menu->id, 'qty' => 2, 'option_ids' => $opts,
            'price' => 1, 'unit_price' => 1, 'line_total' => 1, // manipulasi harga
        ]], 'A1', ['total' => 100, 'discount' => 999999]);

        $res->assertCreated();
        $bill = Bill::where('tracking_key', $res->json('data.tracking_key'))->firstOrFail();
        $this->assertSame(66000, $bill->subtotal); // (28000 + 5000) x 2
        $this->assertSame(6600, $bill->tax);        // 10%
        $this->assertSame(72600, $bill->total);
        $this->assertSame(0, $bill->discount_amount);
        $this->assertSame('occupied', $this->table('A1')->status->value);
    }

    public function test_repeat_orders_on_same_table_go_to_same_bill(): void
    {
        $menu = $this->menu('Es Teh Manis');
        $a = $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => $this->defaultOptions($menu)]])->assertCreated();
        $b = $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => $this->defaultOptions($menu)]])->assertCreated();
        $this->assertSame($a->json('data.tracking_key'), $b->json('data.tracking_key'));
        $this->assertSame(1, Bill::count());
    }

    public function test_unavailable_menu_is_rejected(): void
    {
        $menu = $this->menu('Mocha');
        $menu->update(['is_available' => false]);
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => $this->defaultOptions($menu)]])
            ->assertStatus(422)->assertJsonPath('message', 'Menu "Mocha" sedang tidak tersedia.');
    }

    public function test_missing_required_option_and_foreign_option_rejected(): void
    {
        $menu = $this->menu('Cappuccino');
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => []]])->assertStatus(422);

        $food = $this->menu('Nasi Goreng Spesial');
        $foreign = $this->defaultOptions($food); // opsi level pedas, bukan milik cappuccino
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => array_merge($this->defaultOptions($menu), $foreign)]])
            ->assertStatus(422);
    }

    public function test_qty_limit_honeypot_and_invalid_token(): void
    {
        $menu = $this->menu('Americano');
        $opts = $this->defaultOptions($menu);
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 21, 'option_ids' => $opts]])->assertStatus(422);
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => $opts]], 'A1', ['website' => 'spam'])->assertStatus(422);
        $this->postJson('/api/v1/public/orders', ['table_token' => str_repeat('x', 48), 'items' => [['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => $opts]]])->assertStatus(404);
    }

    public function test_store_closed_blocks_order(): void
    {
        Settings::set(['store_closed' => true]);
        $menu = $this->menu('Americano');
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => $this->defaultOptions($menu)]])->assertStatus(423);
    }

    public function test_takeaway_gets_queue_number_and_requires_contact(): void
    {
        $menu = $this->menu('French Fries');
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1]], null, ['mode' => 'takeaway'])->assertStatus(422);
        $res = $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1]], null, ['mode' => 'takeaway', 'customer_name' => 'Andi', 'customer_phone' => '081234567899'])->assertCreated();
        $this->assertSame(1, $res->json('data.queue_no'));
    }

    public function test_confirmation_mode_holds_order_from_kitchen(): void
    {
        Settings::set(['require_confirmation' => true]);
        $menu = $this->menu('Americano');
        $res = $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => $this->defaultOptions($menu)]])->assertCreated();
        $this->assertSame('pending_confirmation', $res->json('data.status'));

        $this->actingAsRole('barista');
        $this->getJson('/api/v1/kds?station=bar')->assertOk()->assertJsonCount(0, 'data.cards');

        $this->actingAsRole('pelayan');
        $order = Bill::first()->orders()->first();
        $this->postJson("/api/v1/orders/{$order->id}/confirm")->assertOk();

        $this->actingAsRole('barista');
        $this->getJson('/api/v1/kds?station=bar')->assertOk()->assertJsonCount(1, 'data.cards');
    }

    public function test_tracking_returns_status_and_supports_since(): void
    {
        $menu = $this->menu('Americano');
        $key = $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1, 'option_ids' => $this->defaultOptions($menu)]])->json('data.tracking_key');
        $res = $this->getJson("/api/v1/public/track/{$key}")->assertOk()->assertJsonPath('data.changed', true)->assertJsonPath('data.progress', 'new');
        $this->assertNotNull($res->json('meta.version'));
        $this->assertArrayNotHasKey('tracking_key', $res->json('data.bill'));
    }
}
