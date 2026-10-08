<?php

namespace Tests\Feature\Web;

use App\Enums\LeadStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjaxListingTest extends TestCase
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

    private function ajax(string $url, array $body = [])
    {
        return $this->post($url, $body, ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html']);
    }

    public function test_listing_pages_render_an_ajax_region(): void
    {
        foreach (['/leads' => '/leads/table', '/customers' => '/customers/table', '/users' => '/users/table', '/activity' => '/activity/table'] as $page => $endpoint) {
            $this->actingAs($this->admin)->get($page)->assertOk()
                ->assertSee('data-ajax-listing', false)
                ->assertSee('data-endpoint="'.url($endpoint).'"', false);
        }
    }

    public function test_leads_fragment_filters_from_post_body_and_returns_only_the_region(): void
    {
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Wanted Winner', 'status' => LeadStatus::Lost]);
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Other Person', 'status' => LeadStatus::New]);

        $html = $this->actingAs($this->admin)->ajax('/leads/table', ['status' => 'lost', 'search' => 'wanted'])
            ->assertOk()
            ->assertSee('Wanted Winner')
            ->assertDontSee('Other Person')
            ->assertSeeText('of 1')
            ->getContent();

        // Only the fragment: no layout, no <html>, no sidebar.
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('sidebar', $html);
    }

    public function test_fragment_links_point_to_the_clean_listing_url_never_the_endpoint(): void
    {
        Lead::factory(20)->assignedTo($this->sales)->create(['status' => LeadStatus::New]);

        $html = $this->actingAs($this->admin)->ajax('/leads/table', ['status' => 'new', 'per_page' => 5])->getContent();

        $this->assertStringNotContainsString('/leads/table', $html);
        $this->assertStringContainsString(url('/leads').'?status=new&amp;per_page=5&amp;page=2', $html);  // pager keeps state
        $this->assertStringContainsString('sort=name', $html);                                       // sort links keep state
    }

    public function test_sales_scoping_also_applies_to_fragments(): void
    {
        $other = User::factory()->sales()->create();
        Lead::factory()->assignedTo($this->sales)->create(['name' => 'Mine Lead']);
        Lead::factory()->assignedTo($other)->create(['name' => 'Their Lead']);

        $this->actingAs($this->sales)->ajax('/leads/table', ['assigned_to' => $other->id])
            ->assertOk()->assertSee('Mine Lead')->assertDontSee('Their Lead');

        $this->actingAs($this->sales)->ajax('/users/table')->assertForbidden();
        $this->actingAs($this->sales)->ajax('/activity/table')->assertForbidden();
    }

    public function test_guests_cannot_fetch_fragments(): void
    {
        $this->ajax('/leads/table')->assertRedirect('/login');
        $this->ajax('/customers/table')->assertRedirect('/login');
    }

    public function test_fragments_are_post_only(): void
    {
        $this->actingAs($this->admin)->get('/customers/table')->assertStatus(405);
    }

    public function test_tampered_fragment_input_is_ignored_not_an_error(): void
    {
        Lead::factory(2)->assignedTo($this->sales)->create();

        $this->actingAs($this->admin)->ajax('/leads/table', [
            'status' => ['x'], 'source' => ['y'], 'assigned_to' => ['z'], 'search' => ['a'],
            'sort' => 'password', 'direction' => 'sideways', 'per_page' => 99999, 'page' => -3,
            'evil' => '<script>alert(1)</script>',
        ])->assertOk()->assertSeeText('of 2')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_search_terms_are_escaped_in_the_fragment(): void
    {
        $this->actingAs($this->admin)->ajax('/customers/table', ['search' => '"><img src=x onerror=alert(1)>'])
            ->assertOk()
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_customers_users_and_activity_fragments_filter(): void
    {
        Customer::factory()->create(['name' => 'Findable Customer']);
        Customer::factory()->create(['name' => 'Hidden Customer']);
        $this->actingAs($this->admin)->ajax('/customers/table', ['search' => 'findable'])
            ->assertOk()->assertSee('Findable Customer')->assertDontSee('Hidden Customer');

        User::factory()->sales()->inactive()->create(['name' => 'Dormant Person']);
        $this->actingAs($this->admin)->ajax('/users/table', ['status' => 'inactive'])
            ->assertOk()->assertSee('Dormant Person')->assertDontSee($this->sales->name);

        ActivityLog::create(['event' => 'auth.login_failed', 'description' => 'Failed sign-in attempt']);
        ActivityLog::create(['event' => 'lead.created', 'description' => 'Created lead "Somebody"']);
        $this->actingAs($this->admin)->ajax('/activity/table', ['group' => 'security'])
            ->assertOk()->assertSee('Failed sign-in attempt')->assertDontSee('Created lead');
    }
}
