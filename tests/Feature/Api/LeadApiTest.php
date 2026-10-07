<?php

namespace Tests\Feature\Api;

use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sales;

    private User $otherSales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->sales = User::factory()->sales()->create();
        $this->otherSales = User::factory()->sales()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'Jane.Doe@Example.com',
            'phone' => '+91 98765 43210',
            'company' => 'Acme Ltd',
            'source' => 'web',
            'status' => 'new',
            'follow_up_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Interested in the enterprise plan.',
            ...$overrides,
        ];
    }

    // ---- Listing, pagination, search -------------------------------------

    public function test_admin_lists_all_leads_paginated(): void
    {
        Lead::factory(20)->assignedTo($this->sales)->create();
        Lead::factory(5)->assignedTo($this->otherSales)->create();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/leads?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonStructure(['data' => [['id', 'name', 'status', 'status_label', 'assignee', 'customer_id']], 'links', 'meta']);
    }

    public function test_sales_user_only_sees_own_leads(): void
    {
        Lead::factory(3)->assignedTo($this->sales)->create();
        Lead::factory(4)->assignedTo($this->otherSales)->create();
        Sanctum::actingAs($this->sales);

        $response = $this->getJson('/api/leads')->assertOk()->assertJsonPath('meta.total', 3);

        $this->assertSame([$this->sales->id], collect($response->json('data'))->pluck('assigned_to')->unique()->values()->all());
    }

    public function test_search_matches_name_email_phone_and_company(): void
    {
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Zebulon Quark', 'company' => 'Other']);
        Lead::factory()->assignedTo($this->sales)->create(['company' => 'Quarkware Inc']);
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Nobody', 'company' => 'Nothing']);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/leads?search=quark')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_search_treats_wildcards_literally(): void
    {
        Lead::factory(3)->assignedTo($this->sales)->create();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/leads?search=%25')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_filters_by_status_and_source(): void
    {
        Lead::factory(2)->assignedTo($this->sales)->create(['status' => LeadStatus::Lost, 'source' => 'ads']);
        Lead::factory(3)->assignedTo($this->sales)->create(['status' => LeadStatus::New, 'source' => 'ads']);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/leads?status=lost&source=ads')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_invalid_listing_parameters_are_rejected(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/leads?per_page=500&status=bogus&sort=password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'status', 'sort']);
    }

    public function test_listing_can_be_sorted(): void
    {
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Bravo']);
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Alpha']);
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Charlie']);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/leads?sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.2.name', 'Charlie');
    }

    // ---- Create ------------------------------------------------------------

    public function test_admin_can_create_lead_and_assign_it(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/leads', $this->validPayload(['assigned_to' => $this->sales->id]))
            ->assertCreated()
            ->assertJsonPath('data.email', 'jane.doe@example.com')
            ->assertJsonPath('data.assigned_to', $this->sales->id)
            ->assertJsonPath('data.is_converted', false);

        $this->assertDatabaseHas('leads', ['email' => 'jane.doe@example.com', 'created_by' => $this->admin->id]);
    }

    public function test_sales_user_lead_is_auto_assigned_to_self(): void
    {
        Sanctum::actingAs($this->sales);

        $this->postJson('/api/leads', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.assigned_to', $this->sales->id);
    }

    public function test_sales_user_cannot_assign_lead_to_someone_else(): void
    {
        Sanctum::actingAs($this->sales);

        $this->postJson('/api/leads', $this->validPayload(['assigned_to' => $this->otherSales->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_cannot_assign_to_inactive_user(): void
    {
        $inactive = User::factory()->inactive()->create();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/leads', $this->validPayload(['assigned_to' => $inactive->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_create_validates_input(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/leads', [
            'name' => '',
            'email' => 'not-an-email',
            'phone' => 'abc',
            'source' => 'tv',
            'status' => 'maybe',
            'follow_up_date' => now()->subDay()->toDateString(),
            'notes' => str_repeat('a', 5001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'source', 'status', 'follow_up_date', 'notes']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_system_fields_cannot_be_mass_assigned(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/leads', $this->validPayload([
            'customer_id' => $customer->id,
            'converted_at' => now()->toDateTimeString(),
            'created_by' => $this->sales->id,
        ]))->assertCreated()->json('data.id');

        $lead = Lead::find($id);
        $this->assertNull($lead->customer_id);
        $this->assertNull($lead->converted_at);
        $this->assertSame($this->admin->id, $lead->created_by);
    }

    // ---- Lead -> Customer conversion --------------------------------------

    public function test_creating_a_won_lead_converts_it_to_a_customer(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/leads', $this->validPayload(['status' => 'won']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'won')
            ->assertJsonPath('data.is_converted', true)
            ->assertJsonPath('data.customer.email', 'jane.doe@example.com');

        $this->assertDatabaseHas('customers', [
            'id' => $response->json('data.customer_id'),
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '+91 98765 43210',
            'company' => 'Acme Ltd',
        ]);
    }

    public function test_updating_status_to_won_converts_lead(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->status(LeadStatus::InProgress)->create();
        Sanctum::actingAs($this->sales);

        $this->patchJson("/api/leads/{$lead->id}", ['status' => 'won'])
            ->assertOk()
            ->assertJsonPath('data.is_converted', true);

        $lead->refresh();
        $this->assertNotNull($lead->customer_id);
        $this->assertNotNull($lead->converted_at);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_explicit_convert_endpoint_marks_won_and_converts(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create();
        Sanctum::actingAs($this->sales);

        $this->postJson("/api/leads/{$lead->id}/convert")
            ->assertOk()
            ->assertJsonPath('data.status', 'won')
            ->assertJsonPath('data.is_converted', true);

        // Converting again is forbidden (already converted).
        $this->postJson("/api/leads/{$lead->id}/convert")->assertForbidden();
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_status_of_converted_lead_cannot_change(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create();
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/leads/{$lead->id}/convert")->assertOk();

        $this->patchJson("/api/leads/{$lead->id}", ['status' => 'lost'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertSame(LeadStatus::Won, $lead->fresh()->status);
    }

    public function test_converted_lead_other_fields_remain_editable(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create();
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/leads/{$lead->id}/convert")->assertOk();

        $this->patchJson("/api/leads/{$lead->id}", ['notes' => 'Signed contract.', 'status' => 'won'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Signed contract.');
    }

    // ---- Show / update / delete authorization -----------------------------

    public function test_sales_user_cannot_view_or_update_another_users_lead(): void
    {
        $lead = Lead::factory()->assignedTo($this->otherSales)->create();
        Sanctum::actingAs($this->sales);

        $this->getJson("/api/leads/{$lead->id}")->assertForbidden();
        $this->patchJson("/api/leads/{$lead->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->postJson("/api/leads/{$lead->id}/convert")->assertForbidden();

        $this->assertNotSame('Hacked', $lead->fresh()->name);
    }

    public function test_sales_user_cannot_unassign_or_reassign_their_lead(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create();
        Sanctum::actingAs($this->sales);

        $this->patchJson("/api/leads/{$lead->id}", ['assigned_to' => null])->assertUnprocessable();
        $this->patchJson("/api/leads/{$lead->id}", ['assigned_to' => $this->otherSales->id])->assertUnprocessable();

        $this->assertSame($this->sales->id, $lead->fresh()->assigned_to);
    }

    public function test_put_requires_full_payload(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create();
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/leads/{$lead->id}", ['name' => 'Only Name'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'source', 'status']);
    }

    public function test_past_follow_up_date_can_be_kept_on_update(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create(['follow_up_date' => now()->subWeek()->toDateString()]);
        Sanctum::actingAs($this->admin);

        $payload = $this->validPayload(['follow_up_date' => $lead->follow_up_date->toDateString()]);

        $this->putJson("/api/leads/{$lead->id}", $payload)->assertOk();
        $this->putJson("/api/leads/{$lead->id}", [...$payload, 'follow_up_date' => now()->subDays(2)->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('follow_up_date');
    }

    public function test_only_admin_can_delete_leads(): void
    {
        $lead = Lead::factory()->assignedTo($this->sales)->create();

        Sanctum::actingAs($this->sales);
        $this->deleteJson("/api/leads/{$lead->id}")->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/leads/{$lead->id}")->assertNoContent();

        $this->assertSoftDeleted($lead);
        $this->getJson("/api/leads/{$lead->id}")->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
    }
}
