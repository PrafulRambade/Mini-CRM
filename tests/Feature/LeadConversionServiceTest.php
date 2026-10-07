<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\LeadConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeadConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LeadConversionService::class);
    }

    public function test_conversion_creates_customer_and_links_lead(): void
    {
        $lead = Lead::factory()->create(['email' => 'Buyer@Example.com']);

        $customer = $this->service->convert($lead);

        $this->assertSame('buyer@example.com', $customer->email);
        $this->assertSame($lead->name, $customer->name);
        $this->assertSame(LeadStatus::Won, $lead->fresh()->status);
        $this->assertSame($customer->id, $lead->fresh()->customer_id);
        $this->assertNotNull($lead->fresh()->converted_at);
    }

    public function test_conversion_is_idempotent(): void
    {
        $lead = Lead::factory()->create();

        $first = $this->service->convert($lead);
        $second = $this->service->convert($lead->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_leads_with_same_email_share_one_customer(): void
    {
        $a = Lead::factory()->create(['email' => 'same@example.com']);
        $b = Lead::factory()->create(['email' => 'SAME@example.com']);

        $this->service->convert($a);
        $this->service->convert($b);

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame($a->fresh()->customer_id, $b->fresh()->customer_id);
    }

    public function test_soft_deleted_customer_is_restored_instead_of_duplicated(): void
    {
        $customer = Customer::factory()->create(['email' => 'back@example.com']);
        $customer->delete();

        $lead = Lead::factory()->create(['email' => 'back@example.com']);
        $result = $this->service->convert($lead);

        $this->assertSame($customer->id, $result->id);
        $this->assertNotSoftDeleted($customer);
        $this->assertDatabaseCount('customers', 1);
    }
}
