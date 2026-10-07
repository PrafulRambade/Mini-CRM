<?php

namespace App\Services;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates for the dashboard, always scoped to what the user may see.
 */
class DashboardStats
{
    public const TREND_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $byStatus = $this->countBy($user, 'status');
        $bySource = $this->countBy($user, 'source');
        $won = (int) ($byStatus[LeadStatus::Won->value] ?? 0);
        $lost = (int) ($byStatus[LeadStatus::Lost->value] ?? 0);
        $open = (int) ($byStatus[LeadStatus::New->value] ?? 0) + (int) ($byStatus[LeadStatus::InProgress->value] ?? 0);

        return [
            'kpis' => [
                'total_leads' => (int) $byStatus->sum(),
                'open_leads' => $open,
                'customers' => Customer::query()->visibleTo($user)->count(),
                // Win rate over closed deals only (won / (won + lost)).
                'win_rate' => $won + $lost > 0 ? round($won / ($won + $lost) * 100, 1) : null,
                'overdue_followups' => Lead::query()->visibleTo($user)
                    ->whereIn('status', [LeadStatus::New->value, LeadStatus::InProgress->value])
                    ->whereDate('follow_up_date', '<', today())
                    ->count(),
                'new_this_month' => Lead::query()->visibleTo($user)
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count(),
            ],
            'by_status' => collect(LeadStatus::cases())->map(fn (LeadStatus $s) => [
                'key' => $s->value,
                'label' => $s->label(),
                'value' => (int) ($byStatus[$s->value] ?? 0),
            ])->all(),
            'by_source' => collect(LeadSource::cases())->map(fn (LeadSource $s) => [
                'label' => $s->label(),
                'value' => (int) ($bySource[$s->value] ?? 0),
            ])->all(),
            'trend' => $this->trend($user),
            'upcoming_followups' => Lead::query()->visibleTo($user)
                ->with('assignee')
                ->whereIn('status', [LeadStatus::New->value, LeadStatus::InProgress->value])
                ->whereNotNull('follow_up_date')
                ->whereDate('follow_up_date', '<=', today()->addDays(7))
                ->orderBy('follow_up_date')
                ->limit(8)
                ->get(),
            'recent_conversions' => Lead::query()->visibleTo($user)
                ->with(['customer', 'assignee'])
                ->whereNotNull('converted_at')
                ->latest('converted_at')
                ->limit(6)
                ->get(),
            'team' => $user->isAdmin() ? $this->teamPerformance() : collect(),
        ];
    }

    /**
     * @param  'status'|'source'  $column  internal constant, never user input
     */
    private function countBy(User $user, string $column): Collection
    {
        return Lead::query()->visibleTo($user)
            ->toBase()
            ->selectRaw("{$column} as k, COUNT(*) as c")
            ->groupBy($column)
            ->pluck('c', 'k');
    }

    /**
     * Daily leads created vs. converted over the trailing window.
     *
     * @return array{labels: list<string>, created: list<int>, converted: list<int>}
     */
    private function trend(User $user): array
    {
        $start = today()->subDays(self::TREND_DAYS - 1);

        $created = Lead::query()->visibleTo($user)->toBase()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $converted = Lead::query()->visibleTo($user)->toBase()
            ->where('converted_at', '>=', $start)
            ->selectRaw('DATE(converted_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = $createdSeries = $convertedSeries = [];

        for ($day = $start->copy(); $day->lte(today()); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('d M');
            $createdSeries[] = (int) ($created[$key] ?? 0);
            $convertedSeries[] = (int) ($converted[$key] ?? 0);
        }

        return ['labels' => $labels, 'created' => $createdSeries, 'converted' => $convertedSeries];
    }

    private function teamPerformance(): Collection
    {
        return User::query()
            ->where('role', UserRole::Sales->value)
            ->withCount([
                'assignedLeads as total_count',
                'assignedLeads as open_count' => fn ($q) => $q->whereIn('status', [LeadStatus::New->value, LeadStatus::InProgress->value]),
                'assignedLeads as won_count' => fn ($q) => $q->where('status', LeadStatus::Won->value),
                'assignedLeads as lost_count' => fn ($q) => $q->where('status', LeadStatus::Lost->value),
            ])
            ->orderByDesc('won_count')
            ->limit(8)
            ->get()
            ->each(function (User $u) {
                $closed = $u->won_count + $u->lost_count;
                $u->setAttribute('win_rate', $closed > 0 ? round($u->won_count / $closed * 100) : null);
            });
    }

    public static function greeting(?Carbon $now = null): string
    {
        $hour = ($now ?? now())->hour;

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
