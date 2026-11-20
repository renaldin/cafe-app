<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCafe();
    }

    public function test_login_returns_token_and_permissions(): void
    {
        $res = $this->postJson('/api/v1/auth/login', ['username' => 'owner', 'password' => 'GantiPassword123!'])->assertOk();
        $this->assertNotEmpty($res->json('data.token'));
        $this->assertContains('setting.manage', $res->json('data.permissions'));

        $this->withToken($res->json('data.token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.username', 'owner');
    }

    public function test_wrong_password_and_rate_limit(): void
    {
        RateLimiter::clear('owner|127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['username' => 'owner', 'password' => 'salah'])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/login', ['username' => 'owner', 'password' => 'salah'])->assertStatus(429);
    }

    public function test_unauthenticated_gets_401_in_indonesian(): void
    {
        $this->getJson('/api/v1/bills')->assertStatus(401)->assertJsonPath('message', 'Sesi Anda telah berakhir. Silakan login kembali.');
    }

    public function test_inactive_user_rejected(): void
    {
        User::where('username', 'kasir')->update(['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['username' => 'kasir', 'password' => 'password123'])->assertStatus(422);
    }

    public function test_role_permissions_are_enforced_on_endpoints(): void
    {
        $matrix = [
            // [user, method, url, expectedAllowed]
            ['dapur', 'getJson', '/api/v1/kds?station=kitchen', true],
            ['dapur', 'getJson', '/api/v1/bills', false],
            ['dapur', 'getJson', '/api/v1/reports/sales?from=2026-01-01&to=2026-01-31', false],
            ['pelayan', 'getJson', '/api/v1/tables', true],
            ['pelayan', 'getJson', '/api/v1/settings', false],
            ['pelayan', 'getJson', '/api/v1/ingredients', false],
            ['kasir', 'getJson', '/api/v1/orders/feed', true],
            ['kasir', 'getJson', '/api/v1/users', false],
            ['kasir', 'getJson', '/api/v1/reports/profit-loss?from=2026-01-01&to=2026-01-31', false],
            ['manajer', 'getJson', '/api/v1/reports/sales?from=2026-01-01&to=2026-01-31', true],
            ['manajer', 'getJson', '/api/v1/reports/profit-loss?from=2026-01-01&to=2026-01-31', false],
            ['manajer', 'getJson', '/api/v1/roles', false],
            ['owner', 'getJson', '/api/v1/reports/profit-loss?from=2026-01-01&to=2026-01-31', true],
            ['owner', 'getJson', '/api/v1/roles', true],
            ['owner', 'getJson', '/api/v1/settings', true],
        ];
        foreach ($matrix as [$user, $method, $url, $allowed]) {
            $this->actingAsRole($user);
            $res = $this->{$method}($url);
            $allowed
                ? $this->assertTrue($res->status() < 400, "$user harus boleh akses $url (dapat {$res->status()})")
                : $this->assertSame(403, $res->status(), "$user tidak boleh akses $url");
        }
    }

    public function test_owner_can_create_custom_role_with_permissions(): void
    {
        $this->actingAsRole('owner');
        $this->postJson('/api/v1/roles', ['name' => 'Supervisor', 'permissions' => ['report.view', 'bill.view']])->assertCreated();
        $this->postJson('/api/v1/users', ['name' => 'Sup', 'username' => 'sup', 'password' => 'rahasia123', 'role' => 'Supervisor', 'is_active' => true])->assertCreated();

        $this->actingAsRole('sup');
        $this->getJson('/api/v1/bills')->assertOk();
        $this->getJson('/api/v1/menus')->assertStatus(403);
    }

    public function test_manager_cannot_create_owner(): void
    {
        $this->actingAsRole('manajer');
        $this->postJson('/api/v1/users', ['name' => 'X', 'username' => 'x', 'password' => 'rahasia123', 'role' => 'Owner'])->assertStatus(403);
    }
}
