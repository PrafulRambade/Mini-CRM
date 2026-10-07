<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.demo_mode' => true]);
        $this->seed(DemoDataSeeder::class);

        $this->admin = User::where('email', 'admin@crm.test')->firstOrFail();
        $this->sales = User::where('email', 'sales1@crm.test')->firstOrFail();
    }

    public function test_demo_seeder_builds_a_complete_dataset_without_faker(): void
    {
        $this->assertSame(3, User::count());
        $this->assertSame(48, Lead::count());
        $this->assertSame(8, Customer::count());
        $this->assertSame(8, Lead::whereNotNull('customer_id')->count());
        $this->assertTrue(Hash::check('Password@123', $this->admin->password));
    }

    public function test_demo_admin_cannot_change_own_password_or_email(): void
    {
        $this->actingAs($this->admin)->put('/profile/password', [
            'current_password' => 'Password@123', 'password' => 'Hijack123', 'password_confirmation' => 'Hijack123',
        ])->assertSessionHasErrorsIn('password', 'password');

        $this->actingAs($this->admin)->put('/profile', ['name' => 'Renamed', 'email' => 'evil@example.com'])
            ->assertSessionHasErrorsIn('profile', 'email');

        $fresh = $this->admin->fresh();
        $this->assertTrue(Hash::check('Password@123', $fresh->password));
        $this->assertSame('admin@crm.test', $fresh->email);
    }

    public function test_demo_accounts_cannot_be_hijacked_through_user_management(): void
    {
        $this->actingAs($this->admin)->put("/users/{$this->sales->id}", [
            'name' => 'Sales One', 'email' => 'evil@example.com', 'role' => 'admin', 'is_active' => '0',
            'password' => 'Hijack123', 'password_confirmation' => 'Hijack123',
        ])->assertSessionHasErrors(['email', 'role', 'is_active', 'password']);

        $this->actingAs($this->admin)->patch("/users/{$this->sales->id}/toggle-status")->assertForbidden();

        $fresh = $this->sales->fresh();
        $this->assertSame('sales1@crm.test', $fresh->email);
        $this->assertSame(UserRole::Sales, $fresh->role);
        $this->assertTrue($fresh->is_active);
        $this->assertTrue(Hash::check('Password@123', $fresh->password));
    }

    public function test_demo_account_name_can_still_be_edited(): void
    {
        $this->actingAs($this->admin)->put("/users/{$this->sales->id}", [
            'name' => 'Sales Champion', 'email' => 'sales1@crm.test', 'role' => 'sales', 'is_active' => '1',
            'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Sales Champion', $this->sales->fresh()->name);
        $this->actingAs($this->admin)->get("/users/{$this->sales->id}/edit")->assertOk()->assertSee('protected');
    }

    public function test_non_demo_users_are_unaffected(): void
    {
        $other = User::factory()->sales()->create();

        $this->actingAs($this->admin)->patch("/users/{$other->id}/toggle-status")->assertRedirect();
        $this->assertFalse($other->fresh()->is_active);
    }

    public function test_protection_is_off_when_demo_mode_is_disabled(): void
    {
        config(['app.demo_mode' => false]);

        $this->assertFalse($this->sales->isProtectedDemoAccount());
        $this->actingAs($this->admin)->patch("/users/{$this->sales->id}/toggle-status")->assertRedirect();
        $this->assertFalse($this->sales->fresh()->is_active);
    }

    public function test_reset_command_requires_force_and_restores_everything(): void
    {
        // Simulate a visitor vandalising the demo.
        Lead::query()->delete();
        User::factory()->admin()->create(['email' => 'intruder@example.com']);
        $this->sales->forceFill(['is_active' => false, 'name' => 'Broken'])->save();

        $this->artisan('crm:reset-demo')->assertFailed();
        $this->assertSame(0, Lead::count(), 'Nothing should happen without --force.');

        $this->artisan('crm:reset-demo', ['--force' => true])->assertSuccessful();

        $this->assertSame(3, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
        $this->assertSame(48, Lead::count());
        $this->assertSame(8, Customer::count());
        $this->assertTrue($this->sales->fresh()->is_active);
        $this->assertSame('Sales One', $this->sales->fresh()->name);
    }
}
