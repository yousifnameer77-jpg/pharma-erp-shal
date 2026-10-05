<?php

namespace Tests\Feature;

use Tests\Support;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use Support;

    public function test_login_returns_token_for_valid_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'Passw0rd!'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $this->admin()->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'Passw0rd!'])
            ->assertStatus(422);
    }

    public function test_protected_routes_require_token(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = \App\Models\User::create([
            'branch_id' => \App\Models\Branch::firstOrFail()->id,
            'username' => 'noperms',
            'email' => 'n@example.com',
            'password' => 'Passw0rd!',
            'full_name' => 'No Perms',
            'is_active' => true,
        ]);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_admin_can_access_protected_routes(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.username', 'admin');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'bad']);
        }

        $this->postJson('/api/v1/auth/login', ['username' => 'admin', 'password' => 'Passw0rd!'])
            ->assertStatus(429);
    }
}
