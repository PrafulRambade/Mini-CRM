<?php

namespace Tests\Feature\Web;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\DashboardStats;
use App\Services\LeadConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorPagesAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_page_is_branded(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs(User::factory()->sales()->create())
            ->get("/leads/{$lead->id}")
            ->assertForbidden()
            ->assertSee('Access denied');
    }

    public function test_missing_page_is_branded(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/leads/999999')
            ->assertNotFound()
            ->assertSee('Page not found');
    }

    public function test_dashboard_stats_are_scoped_and_correct(): void
    {
        $sales = User::factory()->sales()->create();
        $other = User::factory()->sales()->create();

        Lead::factory(3)->assignedTo($sales)->status(LeadStatus::New)->create();
        Lead::factory()->assignedTo($sales)->status(LeadStatus::Lost)->create();
        $won = Lead::factory()->assignedTo($sales)->create();
        app(LeadConversionService::class)->convert($won);
        Lead::factory(5)->assignedTo($other)->create();

        $stats = app(DashboardStats::class)->for($sales);

        $this->assertSame(5, $stats['kpis']['total_leads']);
        $this->assertSame(3, $stats['kpis']['open_leads']);
        $this->assertSame(1, $stats['kpis']['customers']);
        $this->assertEquals(50.0, $stats['kpis']['win_rate']); // 1 won / (1 won + 1 lost)
        $this->assertCount(DashboardStats::TREND_DAYS, $stats['trend']['labels']);
        $this->assertSame(5, array_sum($stats['trend']['created']));
        $this->assertSame(1, array_sum($stats['trend']['converted']));
        $this->assertTrue($stats['team']->isEmpty(), 'Team table is admin-only.');

        $admin = User::factory()->admin()->create();
        $this->assertSame(10, app(DashboardStats::class)->for($admin)['kpis']['total_leads']);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Team performance');
        $this->actingAs($sales)->get('/dashboard')->assertOk()->assertDontSee('Team performance');
    }

    public function test_dashboard_handles_empty_database(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('No follow-ups due');
    }
}
