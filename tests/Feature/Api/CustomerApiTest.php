<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    private function convertedLeadFor(User $user, array $attributes = []): Lead
    {
        $lead = Lead::factory()->assignedTo($user)->create($attributes);
        app(LeadConversionService::class)->convert($lead);

        return $lead;
    }

    public function test_admin_lists_all_customers_paginated(): void
    {
        Customer::factory(30)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/customers?per_page=25')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'phone', 'company', 'leads']], 'links', 'meta']);
    }

    public function test_customers_can_be_searched(): void
    {
        Customer::factory()->create(['name' => 'Findable Person']);
        Customer::factory()->create(['company' => 'Findable Corp']);
        Customer::factory(5)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/customers?search=findable')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_sales_user_sees_only_customers_from_their_leads(): void
    {
        $sales = User::factory()->sales()->create();
        $other = User::factory()->sales()->create();

        $mine = $this->convertedLeadFor($sales);
        $this->convertedLeadFor($other);
        Customer::factory(3)->create();

        Sanctum::actingAs($sales);

        $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $mine->customer_id)
            ->assertJsonPath('data.0.leads.0.id', $mine->id);
    }

    public function test_sales_user_cannot_view_unrelated_customer(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs(User::factory()->sales()->create());

        $this->getJson("/api/customers/{$customer->id}")->assertForbidden();
    }

    public function test_customers_api_is_read_only(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/customers', ['name' => 'X'])->assertStatus(405);
    }
}
