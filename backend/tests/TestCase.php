<?php

namespace Tests;

use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\Table;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\DemoSeeder;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function seedCafe(): void
    {
        Cache::flush();
        $this->seed([RolePermissionSeeder::class, OwnerSeeder::class, DemoSeeder::class]);
        Settings::flush();
        Settings::set(['tax_enabled' => true, 'tax_percent' => 10, 'service_enabled' => false, 'rounding' => 0, 'enforce_hours' => false, 'store_closed' => false]);
    }

    protected function actingAsRole(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    protected function table(string $code = 'A1'): Table
    {
        return Table::where('code', $code)->firstOrFail();
    }

    protected function menu(string $name): Menu
    {
        return Menu::where('name', $name)->firstOrFail();
    }

    /** Opsi wajib default untuk menu (pilihan pertama setiap grup wajib) */
    protected function defaultOptions(Menu $menu, array $override = []): array
    {
        $ids = [];
        foreach ($menu->modifierGroups()->with('options')->get() as $g) {
            /** @var ModifierGroup $g */
            if (isset($override[$g->name])) {
                $ids[] = $g->options->firstWhere('name', $override[$g->name])->id;
            } elseif ($g->is_required) {
                $ids[] = $g->options->first()->id;
            }
        }

        return $ids;
    }

    protected function placePublicOrder(array $items, ?string $tableCode = 'A1', array $extra = [])
    {
        RateLimiter::clear('ip:127.0.0.1');

        return $this->postJson('/api/v1/public/orders', array_merge([
            'table_token' => $tableCode ? $this->table($tableCode)->qr_token : null,
            'items' => $items,
        ], $extra));
    }
}
