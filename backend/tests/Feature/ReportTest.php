<?php

namespace Tests\Feature;

use App\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_reports_and_dashboard_run(): void
    {
        $this->seedCafe();
        $this->placePublicOrder([['menu_id' => $this->menu('French Fries')->id, 'qty' => 1]])->assertCreated();
        $bill = Bill::first();
        $this->actingAsRole('owner');
        $this->postJson('/api/v1/shifts/open', ['opening_cash' => 0])->assertCreated();
        $this->postJson("/api/v1/bills/{$bill->id}/payments", ['payments' => [['method' => 'cash', 'amount' => $bill->total, 'received' => $bill->total]]])->assertOk();

        $today = now()->toDateString();
        foreach (array_keys(\App\Services\ReportService::TYPES) as $type) {
            $this->getJson("/api/v1/reports/{$type}?from={$today}&to={$today}")->assertOk();
        }
        $this->getJson("/api/v1/reports/sales?from={$today}&to={$today}")->assertJsonPath('data.rows.0.total', $bill->total);
        $this->getJson('/api/v1/dashboard/summary')->assertOk()->assertJsonPath('data.revenue_today', $bill->total);
        $this->getJson('/api/v1/dashboard/charts?range=7d')->assertOk();
    }
}
