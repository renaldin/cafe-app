<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCafe();
    }

    public function test_items_are_routed_to_correct_station(): void
    {
        $coffee = $this->menu('Americano');
        $food = $this->menu('French Fries');
        $this->placePublicOrder([
            ['menu_id' => $coffee->id, 'qty' => 1, 'option_ids' => $this->defaultOptions($coffee)],
            ['menu_id' => $food->id, 'qty' => 1],
        ])->assertCreated();

        $this->actingAsRole('barista');
        $bar = $this->getJson('/api/v1/kds?station=bar')->assertOk();
        $this->assertSame(['Americano'], array_column($bar->json('data.cards.0.items'), 'name'));

        $this->actingAsRole('dapur');
        $kitchen = $this->getJson('/api/v1/kds?station=kitchen')->assertOk();
        $this->assertSame(['French Fries'], array_column($kitchen->json('data.cards.0.items'), 'name'));
    }

    public function test_status_transitions_are_validated(): void
    {
        $menu = $this->menu('French Fries');
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1]])->assertCreated();
        $item = OrderItem::firstOrFail();
        $this->actingAsRole('dapur');

        $this->patchJson("/api/v1/kds/items/{$item->id}", ['status' => 'processing'])->assertOk();
        $this->patchJson("/api/v1/kds/items/{$item->id}", ['status' => 'ready'])->assertOk();
        $this->patchJson("/api/v1/kds/items/{$item->id}", ['status' => 'served'])->assertOk();
        // tidak boleh mundur dari Disajikan
        $this->patchJson("/api/v1/kds/items/{$item->id}", ['status' => 'new'])->assertStatus(422);

        $item->refresh();
        $this->assertNotNull($item->ready_at);
        $this->assertNotNull($item->served_at);
    }

    public function test_bulk_order_status_update(): void
    {
        $a = $this->menu('French Fries');
        $b = $this->menu('Sosis Bakar');
        $this->placePublicOrder([['menu_id' => $a->id, 'qty' => 1], ['menu_id' => $b->id, 'qty' => 2]])->assertCreated();
        $order = Bill::first()->orders()->first();
        $this->actingAsRole('dapur');
        $this->patchJson("/api/v1/kds/orders/{$order->id}", ['status' => 'ready', 'station' => 'kitchen'])->assertOk()->assertJsonPath('data.changed', 2);
        $this->assertSame(0, OrderItem::where('status', '!=', 'ready')->count());
    }

    public function test_cancel_item_after_kitchen_requires_reason_and_permission(): void
    {
        $menu = $this->menu('French Fries');
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 1], ['menu_id' => $this->menu('Sosis Bakar')->id, 'qty' => 1]])->assertCreated();
        $item = OrderItem::firstOrFail();

        $this->actingAsRole('kasir'); // kasir tidak punya order.cancel_item
        $this->deleteJson("/api/v1/order-items/{$item->id}", ['reason' => 'salah input'])->assertStatus(403);

        $this->actingAsRole('manajer');
        $this->deleteJson("/api/v1/order-items/{$item->id}")->assertStatus(422);
        $this->deleteJson("/api/v1/order-items/{$item->id}", ['reason' => 'Pelanggan batal'])->assertOk();
        $this->assertSame('cancelled', $item->fresh()->status->value);
        $this->assertSame(22000 + 2200, Bill::first()->total); // tinggal sosis bakar + pajak
        $this->assertDatabaseHas('activity_logs', ['action' => 'order.cancel_item', 'reason' => 'Pelanggan batal']);
    }
}
