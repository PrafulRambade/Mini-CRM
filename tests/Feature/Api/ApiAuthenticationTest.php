<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_bearer_token(): void
    {
        $user = User::factory()->admin()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'phpunit'])
            ->assertCreated()
            ->assertJsonStructure(['token_type', 'access_token', 'expires_at', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'admin');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_tokens_expire_after_one_hour(): void
    {
        config(['sanctum.expiration' => 60]);
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertCreated();

        $expiresAt = Carbon::parse($response->json('expires_at'));
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, $expiresAt->timestamp, 5);

        $token = $response->json('access_token');
        $this->travel(59)->minutes();
        $this->withToken($token)->getJson('/api/me')->assertOk();

        $this->travel(2)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_token_lifetime_can_never_be_unlimited(): void
    {
        foreach (['0', '', '-5'] as $value) {
            putenv("SANCTUM_TOKEN_EXPIRATION={$value}");
            $_ENV['SANCTUM_TOKEN_EXPIRATION'] = $_SERVER['SANCTUM_TOKEN_EXPIRATION'] = $value;

            $config = require base_path('config/sanctum.php');
            $this->assertSame(1, $config['expiration'], "SANCTUM_TOKEN_EXPIRATION='{$value}' must not disable expiry");
        }

        putenv('SANCTUM_TOKEN_EXPIRATION');
        unset($_ENV['SANCTUM_TOKEN_EXPIRATION'], $_SERVER['SANCTUM_TOKEN_EXPIRATION']);
    }

    public function test_login_with_invalid_credentials_returns_422(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_inactive_user_cannot_obtain_token(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_token_authenticates_requests(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_requests_without_token_get_json_401_even_without_accept_header(): void
    {
        $this->get('/api/leads')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->get('/api/customers')->assertUnauthorized();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('1|not-a-real-token')->getJson('/api/leads')->assertUnauthorized();
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson('/api/leads')->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_deactivated_user_token_is_rejected_and_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $user->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson('/api/leads')->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_endpoint_returns_json_404(): void
    {
        $this->get('/api/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Endpoint not found.']);
    }
}
