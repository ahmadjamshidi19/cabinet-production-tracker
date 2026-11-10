<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_credentials_return_a_token_that_authenticates(): void
    {
        $user = User::factory()->planner()->create();

        $token = $this->postJson('/api/tokens', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertCreated()
            ->assertJsonStructure(['token'])
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/tokens', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_unauthenticated_requests_get_json_even_without_accept_header(): void
    {
        $this->get('/api/user')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
