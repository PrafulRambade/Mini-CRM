<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    // ---- Content-Security-Policy & headers ---------------------------------

    public function test_pages_send_a_strict_csp_whose_nonce_matches_every_script(): void
    {
        $response = $this->get('/login')->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]{20,}'/", $csp);

        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $html = $response->getContent();

        // Every inline <script> carries this request's nonce; no inline event handlers exist.
        preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/i', $html, $inline);
        $this->assertNotEmpty($inline[1]);
        foreach ($inline[1] as $attrs) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $attrs);
        }
        $this->assertDoesNotMatchRegularExpression('/\son(click|load|error|submit|change)=/i', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_csp_is_also_embedded_as_meta_tag_for_hosts_that_rewrite_headers(): void
    {
        $response = $this->get('/login');
        preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m);

        preg_match('/<meta http-equiv="Content-Security-Policy" content="([^"]+)">/', $response->getContent(), $meta);
        $this->assertNotEmpty($meta, 'CSP <meta> tag missing');

        $policy = html_entity_decode($meta[1], ENT_QUOTES);
        $this->assertStringContainsString("script-src 'self' 'nonce-{$m[1]}'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringNotContainsString('frame-ancestors', $policy, 'frame-ancestors is invalid in <meta>');

        // It must come before any script so it governs all of them.
        $this->assertLessThan(strpos($response->getContent(), '<script'), strpos($response->getContent(), 'http-equiv="Content-Security-Policy"'));
    }

    public function test_nonce_is_unique_per_request(): void
    {
        $a = $this->get('/login')->headers->get('Content-Security-Policy');
        $b = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertNotSame($a, $b);
    }

    public function test_no_third_party_assets_are_loaded(): void
    {
        $admin = User::factory()->admin()->create();
        Lead::factory(3)->create();

        foreach (['/login'] as $url) {
            $this->assertNoExternalAssets($this->get($url)->getContent(), $url);
        }
        foreach (['/dashboard', '/leads', '/customers', '/users'] as $url) {
            $this->assertNoExternalAssets($this->actingAs($admin)->get($url)->getContent(), $url);
        }
    }

    public function test_hardening_headers_and_no_version_disclosure(): void
    {
        $this->get('/login')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeaderMissing('X-Powered-By');

        $this->getJson('/api/leads')->assertHeader('Content-Security-Policy');
    }

    public function test_self_hosted_assets_exist(): void
    {
        foreach (['vendor/bootstrap/bootstrap.min.css', 'vendor/bootstrap/bootstrap.bundle.min.js',
            'vendor/bootstrap-icons/bootstrap-icons.min.css', 'vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
            'vendor/chartjs/chart.umd.min.js', 'vendor/inter/inter-latin-wght-normal.woff2'] as $file) {
            $this->assertFileExists(public_path($file));
        }
    }

    // ---- CORS ---------------------------------------------------------------

    public function test_api_cors_only_allows_the_app_origin(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $evil = $this->getJson('/api/leads', ['Origin' => 'https://evil.example']);
        $this->assertNotSame('https://evil.example', $evil->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('*', $evil->headers->get('Access-Control-Allow-Origin'));

        $own = rtrim(config('app.url'), '/');
        $this->getJson('/api/leads', ['Origin' => $own])->assertHeader('Access-Control-Allow-Origin', $own);
    }

    // ---- Audit trail ----------------------------------------------------------

    public function test_failed_and_successful_logins_are_audited_without_passwords(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'WrongSecret999']);
        $this->post('/login', ['email' => 'jane@example.com', 'password' => 'password']);

        $failed = ActivityLog::where('event', 'auth.login_failed')->firstOrFail();
        $this->assertSame('jane@example.com', $failed->properties['email']);
        $this->assertNotNull($failed->ip_address);
        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.login', 'user_id' => $user->id]);

        // Passwords never reach the audit table.
        $this->assertSame(0, ActivityLog::where('properties', 'like', '%WrongSecret999%')->count());
        $this->assertSame(0, ActivityLog::where('properties', 'like', '%password%')->where('event', 'like', 'auth.%')->count());
    }

    public function test_lockout_is_audited(): void
    {
        User::factory()->create(['email' => 'target@example.com']);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'target@example.com', 'password' => 'nope']);
        }

        $this->assertDatabaseHas('activity_logs', ['event' => 'auth.lockout']);
    }

    public function test_lead_lifecycle_is_audited_once_per_action(): void
    {
        $admin = User::factory()->admin()->create();
        $lead = Lead::factory()->status(LeadStatus::New)->create(['notes' => 'Secret pricing discussion']);

        $this->actingAs($admin)->put("/leads/{$lead->id}", [
            'name' => $lead->name, 'email' => $lead->email, 'phone' => $lead->phone,
            'source' => $lead->source->value, 'status' => 'in_progress', 'notes' => 'Changed secret note',
        ]);
        $this->actingAs($admin)->post("/leads/{$lead->id}/convert");
        $this->actingAs($admin)->delete("/leads/{$lead->id}");

        $updated = ActivityLog::where('event', 'lead.updated')->firstOrFail();
        $this->assertSame(['from' => 'new', 'to' => 'in_progress'], $updated->properties['status']);
        $this->assertContains('notes', $updated->properties['fields']);
        $this->assertSame($admin->id, $updated->user_id);

        $this->assertSame(1, ActivityLog::where('event', 'lead.converted')->count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'lead.deleted', 'subject_id' => $lead->id, 'user_id' => $admin->id]);

        // Note contents are never copied into the log.
        $this->assertSame(0, ActivityLog::where('properties', 'like', '%secret%')->count());
    }

    public function test_user_management_actions_are_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = User::factory()->sales()->create();

        $this->actingAs($admin)->patch("/users/{$sales->id}/toggle-status");
        $this->assertDatabaseHas('activity_logs', ['event' => 'user.deactivated', 'subject_id' => $sales->id, 'user_id' => $admin->id]);
    }

    public function test_only_admins_can_view_the_activity_log(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/logout');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $this->actingAs(User::factory()->sales()->create())->get('/activity')->assertForbidden();
        $this->actingAs($admin)->get('/activity')->assertOk()->assertSee('Activity log')->assertSee('Signed in');
        $this->actingAs($admin)->get('/activity?group=security&search[]=x')->assertOk();
    }

    public function test_old_activity_is_pruned(): void
    {
        ActivityLog::create(['event' => 'auth.login', 'description' => 'old', 'created_at' => now()->subDays(ActivityLog::RETENTION_DAYS + 1)]);
        ActivityLog::create(['event' => 'auth.login', 'description' => 'recent']);

        $this->artisan('model:prune', ['--model' => [ActivityLog::class]])->assertSuccessful();

        $this->assertSame(['recent'], ActivityLog::pluck('description')->all());
    }

    private function assertNoExternalAssets(string $html, string $url): void
    {
        preg_match_all('/<(?:script|link)[^>]+(?:src|href)="(https?:)?\/\/(?!localhost)[^"]+"/i', $html, $m);
        $this->assertSame([], $m[0], "External asset found on {$url}");
    }
}
