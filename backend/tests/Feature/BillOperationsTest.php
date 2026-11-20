<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Ingredient;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCafe();
    }

    public function test_split_bill_per_item(): void
    {
        $fries = $this->menu('French Fries'); // 22.000
        $sosis = $this->menu('Sosis Bakar');  // 22.000
        $this->placePublicOrder([['menu_id' => $fries->id, 'qty' => 3], ['menu_id' => $sosis->id, 'qty' => 1]])->assertCreated();
        $bill = Bill::firstOrFail();
        $friesItem = OrderItem::where('menu_id', $fries->id)->first();

        $this->actingAsRole('kasir');
        $res = $this->postJson("/api/v1/bills/{$bill->id}/split", ['items' => [['item_id' => $friesItem->id, 'qty' => 2]]])->assertOk();
        $child = Bill::findOrFail($res->json('data.new_bill.id'));
        $this->assertSame(44000, $child->subtotal);
        $this->assertSame(44000, $bill->fresh()->subtotal);
        $this->assertSame($bill->table_id, $child->table_id);
        $this->assertSame(1, $friesItem->fresh()->qty);
    }

    public function test_cannot_split_all_items(): void
    {
        $this->placePublicOrder([['menu_id' => $this->menu('French Fries')->id, 'qty' => 1]])->assertCreated();
        $bill = Bill::first();
        $item = OrderItem::first();
        $this->actingAsRole('kasir');
        $this->postJson("/api/v1/bills/{$bill->id}/split", ['items' => [['item_id' => $item->id, 'qty' => 1]]])->assertStatus(422);
    }

    public function test_merge_and_move_table(): void
    {
        $fries = $this->menu('French Fries');
        $this->placePublicOrder([['menu_id' => $fries->id, 'qty' => 1]], 'A1')->assertCreated();
        $this->placePublicOrder([['menu_id' => $fries->id, 'qty' => 1]], 'A2')->assertCreated();
        [$a, $b] = Bill::orderBy('id')->get();

        $this->actingAsRole('kasir');
        $this->postJson("/api/v1/bills/{$a->id}/move-table", ['table_id' => $this->table('A2')->id])->assertStatus(422); // terisi
        $this->postJson("/api/v1/bills/{$a->id}/move-table", ['table_id' => $this->table('B1')->id])->assertOk();
        $this->assertSame('occupied', $this->table('B1')->status->value);
        $this->assertSame('needs_cleaning', $this->table('A1')->status->value);

        $this->postJson("/api/v1/bills/{$a->id}/merge", ['bill_ids' => [$b->id]])->assertOk()->assertJsonPath('data.subtotal', 44000);
        $this->assertSame('merged', $b->fresh()->status->value);
        $this->assertSame('empty', $this->table('A2')->status->value);
    }

    public function test_void_requires_permission_and_reason(): void
    {
        $this->placePublicOrder([['menu_id' => $this->menu('French Fries')->id, 'qty' => 1]])->assertCreated();
        $bill = Bill::first();
        $this->actingAsRole('kasir');
        $this->postJson("/api/v1/bills/{$bill->id}/void", ['reason' => 'tes'])->assertStatus(403);
        $this->actingAsRole('manajer');
        $this->postJson("/api/v1/bills/{$bill->id}/void", [])->assertStatus(422);
        $this->postJson("/api/v1/bills/{$bill->id}/void", ['reason' => 'Pesanan dobel'])->assertOk()->assertJsonPath('data.status', 'void');
        $this->assertSame('empty', $this->table('A1')->status->value);
        $this->assertDatabaseHas('activity_logs', ['action' => 'bill.void']);
    }

    public function test_refund_with_restock_returns_ingredients(): void
    {
        $fries = $this->menu('French Fries'); // kentang 150 g
        $potato = Ingredient::where('name', 'Kentang Beku')->first();
        $start = $potato->stock;
        $this->placePublicOrder([['menu_id' => $fries->id, 'qty' => 1]])->assertCreated();
        $bill = Bill::first();

        $this->actingAsRole('manajer');
        $this->postJson('/api/v1/shifts/open', ['opening_cash' => 0])->assertCreated();
        $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'cash', 'amount' => $bill->total, 'received' => $bill->total]]])->assertOk();
        $this->assertEquals($start - 150, $potato->fresh()->stock);

        $this->postJson("/api/v1/bills/{$bill->id}/refund", ['amount' => $bill->total + 1, 'reason' => 'Komplain'])->assertStatus(422);
        $this->postJson("/api/v1/bills/{$bill->id}/refund", ['amount' => $bill->total, 'reason' => 'Komplain rasa', 'restock' => true])
            ->assertOk()->assertJsonPath('data.status', 'refunded');
        $this->assertEquals($start, $potato->fresh()->stock);
    }

    public function test_discount_and_voucher(): void
    {
        $this->placePublicOrder([['menu_id' => $this->menu('French Fries')->id, 'qty' => 3]])->assertCreated(); // 66.000
        $bill = Bill::first();
        $this->actingAsRole('kasir');
        $this->postJson("/api/v1/bills/{$bill->id}/discount", ['type' => 'percent', 'value' => 10, 'reason' => 'x'])->assertStatus(403);
        $this->postJson("/api/v1/bills/{$bill->id}/voucher", ['code' => 'HEMAT10'])->assertOk()->assertJsonPath('data.voucher_amount', 6600);

        $this->actingAsRole('manajer');
        $this->postJson("/api/v1/bills/{$bill->id}/discount", ['type' => 'fixed', 'value' => 6000, 'reason' => 'Pelanggan setia'])
            ->assertOk()->assertJsonPath('data.discount_amount', 6000)->assertJsonPath('data.voucher_amount', 6000);
        // (66.000 - 6.000 - 6.000) x 1,1 = 59.400
        $this->assertSame(59400, $bill->fresh()->total);
    }
}
