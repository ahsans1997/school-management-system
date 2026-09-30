<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'admin@school.test';

    private const PASSWORD = 'password123';

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'email' => self::EMAIL,
            'password' => self::PASSWORD, // hashed by the model cast
            'is_active' => true,
        ], $overrides));
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    private function login(): array
    {
        return $this->postJson('/api/auth/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->json();
    }

    public function test_login_returns_access_and_refresh_tokens(): void
    {
        $this->makeUser();

        $this->postJson('/api/auth/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'is_active'],
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ])
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('user.email', self::EMAIL);
    }

    public function test_login_with_invalid_credentials_is_rejected(): void
    {
        $this->makeUser();

        $this->postJson('/api/auth/login', [
            'email' => self::EMAIL,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_disabled_account_cannot_login(): void
    {
        $this->makeUser(['is_active' => false]);

        $this->postJson('/api/auth/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_rejects_an_invalid_token(): void
    {
        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer not-a-real-token',
        ])->assertUnauthorized();
    }

    public function test_me_returns_the_current_user_with_an_access_token(): void
    {
        $this->makeUser();
        $token = $this->login()['access_token'];

        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('user.email', self::EMAIL);
    }

    public function test_refresh_issues_a_new_access_token(): void
    {
        $this->makeUser();
        $refresh = $this->login()['refresh_token'];

        $this->postJson('/api/auth/refresh', [], ['Authorization' => "Bearer {$refresh}"])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);
    }

    public function test_an_access_token_cannot_be_used_to_refresh(): void
    {
        $this->makeUser();
        $access = $this->login()['access_token'];

        $this->postJson('/api/auth/refresh', [], ['Authorization' => "Bearer {$access}"])
            ->assertUnauthorized();
    }

    public function test_a_refresh_token_cannot_access_a_protected_route(): void
    {
        $this->makeUser();
        $refresh = $this->login()['refresh_token'];

        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$refresh}"])
            ->assertUnauthorized();
    }

    public function test_logout_invalidates_the_token(): void
    {
        $this->makeUser();
        $token = $this->login()['access_token'];

        $this->postJson('/api/auth/logout', [], ['Authorization' => "Bearer {$token}"])
            ->assertOk();

        $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => self::EMAIL,
                'password' => 'wrong',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'email' => self::EMAIL,
            'password' => 'wrong',
        ])->assertStatus(429);
    }
}
