<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCafe();
    }

    private function billWithFries(int $qty = 2): Bill
    {
        $this->placePublicOrder([['menu_id' => $this->menu('French Fries')->id, 'qty' => $qty]])->assertCreated();

        return Bill::firstOrFail(); // 44.000 + 10% = 48.400
    }

    private function openShift(int $cash = 200000): void
    {
        $this->postJson('/api/v1/shifts/open', ['opening_cash' => $cash])->assertCreated();
    }

    public function test_cashier_cannot_pay_without_active_shift(): void
    {
        $bill = $this->billWithFries();
        $this->actingAsRole('kasir');
        $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'cash', 'amount' => 48400, 'received' => 50000]]])
            ->assertStatus(409);
    }

    public function test_cash_payment_with_change_and_table_released(): void
    {
        $bill = $this->billWithFries();
        $this->actingAsRole('kasir');
        $this->openShift();
        $res = $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'cash', 'amount' => 48400, 'received' => 50000]]])->assertOk();
        $res->assertJsonPath('data.status', 'paid')->assertJsonPath('data.change_amount', 1600);
        $this->assertSame('needs_cleaning', $this->table('A1')->status->value);
        $this->assertMatchesRegularExpression('/^CF-\d{8}-\d{4}$/', $bill->invoice_no);
    }

    public function test_double_payment_is_rejected(): void
    {
        $bill = $this->billWithFries();
        $this->actingAsRole('kasir');
        $this->openShift();
        $payload = ['payments' => [['method' => 'qris', 'amount' => 48400]]];
        $this->postJson("/api/v1/bills/{$bill->id}/payments", $payload)->assertOk();
        $this->postJson("/api/v1/bills/{$bill->id}/payments", $payload)->assertStatus(409);
        $this->assertSame(1, $bill->payments()->count());
    }

    public function test_split_payment_methods_and_partial_per_person(): void
    {
        $bill = $this->billWithFries();
        $this->actingAsRole('kasir');
        $this->openShift();
        // orang 1 bayar 20.000 QRIS (parsial)
        $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'qris', 'amount' => 20000]]])
            ->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.balance_due', 28400);
        // sisanya: transfer 10.000 + tunai 20.000 (kembali 1.600)
        $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'transfer', 'amount' => 10000], ['method' => 'cash', 'amount' => 18400, 'received' => 20000]]])
            ->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.change_amount', 1600);
        $this->assertSame(48400, (int) $bill->payments()->sum('amount'));
    }

    public function test_non_cash_overpayment_rejected(): void
    {
        $bill = $this->billWithFries();
        $this->actingAsRole('kasir');
        $this->openShift();
        $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'card', 'amount' => 999999]]])->assertStatus(422);
    }

    public function test_stock_is_deducted_by_recipe_on_payment_including_option(): void
    {
        $menu = $this->menu('Cappuccino'); // susu 150 ml, Large +60 ml
        $milk = Ingredient::where('name', 'Susu Segar')->first();
        $before = $milk->stock;
        $this->placePublicOrder([['menu_id' => $menu->id, 'qty' => 2, 'option_ids' => $this->defaultOptions($menu, ['Ukuran' => 'Large'])]])->assertCreated();
        $this->assertEquals($before, $milk->fresh()->stock); // belum dipotong sebelum bayar

        $bill = Bill::first();
        $this->actingAsRole('kasir');
        $this->openShift();
        $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'cash', 'amount' => $bill->total, 'received' => $bill->total]]])->assertOk();
        $this->assertEquals($before - (150 + 60) * 2, $milk->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['ingredient_id' => $milk->id, 'type' => 'sale']);
    }

    public function test_shift_close_calculates_difference(): void
    {
        $bill = $this->billWithFries();
        $this->actingAsRole('kasir');
        $this->openShift(100000);
        $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'cash', 'amount' => 48400, 'received' => 50000]]])->assertOk();
        $shiftId = $this->getJson('/api/v1/shifts/current')->json('data.id');
        $this->postJson("/api/v1/shifts/{$shiftId}/close", ['counted_cash' => 148000])
            ->assertOk()->assertJsonPath('data.expected_cash', 148400)->assertJsonPath('data.difference', -400);
    }
}
