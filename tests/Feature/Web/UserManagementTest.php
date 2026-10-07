<?php

namespace Tests\Feature\Web;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'New Person',
            'email' => 'New.Person@Example.com',
            'role' => 'sales',
            'is_active' => '1',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            ...$overrides,
        ];
    }

    public function test_sales_users_cannot_access_user_management(): void
    {
        $sales = User::factory()->sales()->create();

        $this->actingAs($sales)->get('/users')->assertForbidden();
        $this->actingAs($sales)->get('/users/create')->assertForbidden();
        $this->actingAs($sales)->post('/users', $this->payload())->assertForbidden();
        $this->actingAs($sales)->get("/users/{$this->admin->id}/edit")->assertForbidden();
        $this->actingAs($sales)->patch("/users/{$this->admin->id}/toggle-status")->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }

    public function test_admin_can_list_search_and_filter_users(): void
    {
        User::factory()->sales()->create(['name' => 'Searchable Seller']);
        User::factory()->sales()->inactive()->create(['name' => 'Dormant Seller']);

        $this->actingAs($this->admin)->get('/users')->assertOk()->assertSee('Searchable Seller')->assertSee('Dormant Seller');
        $this->actingAs($this->admin)->get('/users?search=searchable')->assertOk()->assertSee('Searchable Seller')->assertDontSee('Dormant Seller');
        $this->actingAs($this->admin)->get('/users?status=inactive')->assertOk()->assertSee('Dormant Seller')->assertDontSee('Searchable Seller');
        $this->actingAs($this->admin)->get('/users/create')->assertOk();
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->admin)->post('/users', $this->payload())->assertRedirect('/users');

        $user = User::where('email', 'new.person@example.com')->firstOrFail();
        $this->assertSame(UserRole::Sales, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Secret123', $user->password));
    }

    public function test_create_user_validates_password_strength_and_unique_email(): void
    {
        $this->actingAs($this->admin)
            ->post('/users', $this->payload(['email' => $this->admin->email, 'password' => 'weak', 'password_confirmation' => 'weak', 'role' => 'root']))
            ->assertSessionHasErrors(['email', 'password', 'role']);
    }

    public function test_admin_can_update_user_and_reset_password(): void
    {
        $user = User::factory()->sales()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->actingAs($this->admin)->get("/users/{$user->id}/edit")->assertOk();
        $this->actingAs($this->admin)->put("/users/{$user->id}", $this->payload([
            'email' => $user->email, 'role' => 'admin', 'password' => 'Another123', 'password_confirmation' => 'Another123',
        ]))->assertRedirect('/users');

        $user->refresh();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue(Hash::check('Another123', $user->password));
        $this->assertCount(0, $user->tokens, 'Password reset should revoke API tokens.');
        $this->assertNotNull($token);
    }

    public function test_blank_password_on_update_keeps_existing_password(): void
    {
        $user = User::factory()->sales()->create();

        $this->actingAs($this->admin)->put("/users/{$user->id}", $this->payload([
            'email' => $user->email, 'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect('/users');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        $this->actingAs($this->admin)->put("/users/{$this->admin->id}", $this->payload([
            'email' => $this->admin->email, 'role' => 'sales', 'is_active' => '0', 'password' => '', 'password_confirmation' => '',
        ]))->assertSessionHasErrors(['role', 'is_active']);

        $this->actingAs($this->admin)->patch("/users/{$this->admin->id}/toggle-status")->assertForbidden();

        $this->assertTrue($this->admin->fresh()->isAdmin());
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_deactivating_user_revokes_tokens_and_blocks_login(): void
    {
        $user = User::factory()->sales()->create();
        $user->createToken('t');

        $this->actingAs($this->admin)->patch("/users/{$user->id}/toggle-status")->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->actingAs($this->admin)->patch("/users/{$user->id}/toggle-status");
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_role_cannot_be_escalated_via_profile_update(): void
    {
        $sales = User::factory()->sales()->create();

        $this->actingAs($sales)->put('/profile', ['name' => 'Me', 'email' => $sales->email, 'role' => 'admin', 'is_active' => 1])
            ->assertRedirect();

        $this->assertSame(UserRole::Sales, $sales->fresh()->role);
        $this->assertSame('Me', $sales->fresh()->name);
    }

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'wrong', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewPass123', $user->fresh()->password));
    }
}
