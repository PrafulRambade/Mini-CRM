<?php

namespace App\Providers;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Surface lazy loading, silently discarded attributes and missing
        // attributes as exceptions during development and testing.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Block destructive artisan commands (migrate:fresh, db:wipe...) in production.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Paginator::useBootstrapFive();

        $this->configureRateLimiting();

        // Sidebar badge counts for the admin shell.
        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            $view->with('navCounts', $user ? [
                'open_leads' => Lead::query()->visibleTo($user)
                    ->whereIn('status', [LeadStatus::New->value, LeadStatus::InProgress->value])
                    ->count(),
            ] : []);
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
