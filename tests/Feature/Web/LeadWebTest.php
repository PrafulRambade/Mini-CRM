<?php

namespace Tests\Feature\Web;

use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->sales = User::factory()->sales()->create();
    }

    public function test_dashboard_renders(): void
    {
        Lead::factory(3)->assignedTo($this->sales)->create(['follow_up_date' => now()->addDay()->toDateString()]);

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('Total leads')->assertSee('Lead activity');
    }

    public function test_index_lists_searches_and_paginates(): void
    {
        Lead::factory(20)->assignedTo($this->sales)->create();
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Unique Searchable Name']);

        $this->actingAs($this->admin)->get('/leads')
            ->assertOk()
            ->assertSeeText('of 21');

        $this->actingAs($this->admin)->get('/leads?search=Unique+Searchable')
            ->assertOk()
            ->assertSee('Unique Searchable Name')
            ->assertSeeText('of 1');
    }

    public function test_index_ignores_tampered_query_parameters(): void
    {
        Lead::factory(2)->assignedTo($this->sales)->create();

        $this->actingAs($this->admin)
            ->get('/leads?status=bogus&sort=password&per_page=99999&search[]=x')
            ->assertOk()
            ->assertSeeText('of 2');
    }

    public function test_sales_user_index_only_shows_own_leads(): void
    {
        $other = User::factory()->sales()->create();
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Mine Lead']);
        Lead::factory()->assignedTo($other)->create(['name' => 'Their Lead']);

        $this->actingAs($this->sales)->get('/leads')
            ->assertOk()
            ->assertSee('Mine Lead')
            ->assertDontSee('Their Lead');
    }

    public function test_create_and_edit_forms_render(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create();

        $this->actingAs($this->admin)->get('/leads/create')->assertOk()->assertSee('Owner');
        $this->actingAs($this->sales)->get('/leads/create')->assertOk()->assertDontSee('Unassigned</option>', false);
        $this->actingAs($this->sales)->get("/leads/{$lead->id}/edit")->assertOk();
        $this->actingAs($this->sales)->get("/leads/{$lead->id}")->assertOk()->assertSee('Convert to customer');
    }

    public function test_store_creates_lead(): void
    {
        $this->actingAs($this->sales)->post('/leads', [
            'name' => 'Form Lead',
            'email' => 'form@example.com',
            'phone' => '9876543210',
            'source' => 'referral',
            'status' => 'in_progress',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('leads', ['email' => 'form@example.com', 'assigned_to' => $this->sales->id]);
    }

    public function test_store_with_invalid_data_redirects_back_with_errors(): void
    {
        $this->actingAs($this->sales)->from('/leads/create')
            ->post('/leads', ['name' => '', 'email' => 'bad'])
            ->assertRedirect('/leads/create')
            ->assertSessionHasErrors(['name', 'email', 'phone', 'source']);
    }

    public function test_updating_to_won_converts_and_shows_customer(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->status(LeadStatus::InProgress)->create();

        $this->actingAs($this->sales)->put("/leads/{$lead->id}", [
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'company' => $lead->company,
            'source' => $lead->source->value,
            'status' => 'won',
        ])->assertRedirect("/leads/{$lead->id}")->assertSessionHas('success');

        $this->assertNotNull($lead->fresh()->customer_id);

        $this->actingAs($this->sales)->get('/customers')->assertOk()->assertSee($lead->email);
    }

    public function test_convert_button_converts_lead(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create();

        $this->actingAs($this->sales)->post("/leads/{$lead->id}/convert")->assertRedirect("/leads/{$lead->id}");

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame(LeadStatus::Won, $lead->fresh()->status);
    }

    public function test_sales_user_cannot_access_another_users_lead(): void
    {
        $lead = Lead::factory()->assignedTo(User::factory()->sales()->create())->create();

        $this->actingAs($this->sales)->get("/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($this->sales)->get("/leads/{$lead->id}/edit")->assertForbidden();
        $this->actingAs($this->sales)->delete("/leads/{$lead->id}")->assertForbidden();
    }

    public function test_only_admin_can_delete(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create();

        $this->actingAs($this->sales)->delete("/leads/{$lead->id}")->assertForbidden();
        $this->actingAs($this->admin)->delete("/leads/{$lead->id}")->assertRedirect('/leads');

        $this->assertSoftDeleted($lead);
    }

    public function test_customers_listing_is_searchable_and_paginated(): void
    {
        Customer::factory(20)->create();
        Customer::factory()->create(['name' => 'Very Specific Customer']);

        $this->actingAs($this->admin)->get('/customers')->assertOk()->assertSeeText('of 21');
        $this->actingAs($this->admin)->get('/customers?search=Very+Specific')
            ->assertOk()
            ->assertSee('Very Specific Customer')
            ->assertSeeText('of 1');
    }
}
