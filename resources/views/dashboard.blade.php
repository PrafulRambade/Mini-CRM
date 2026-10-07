@extends('layouts.app')

@section('title', 'Dashboard')

@php
    $k = $stats['kpis'];
    $statusTotal = max(1, array_sum(array_column($stats['by_status'], 'value')));
    $statusTones = ['new' => 'neutral', 'in_progress' => 'primary', 'won' => 'success', 'lost' => 'danger'];
    $trendCreated = array_sum($stats['trend']['created']);
    $trendConverted = array_sum($stats['trend']['converted']);
@endphp

@section('content')
    {{-- Welcome --}}
    <div class="welcome-card mb-4">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <h2>{{ $greeting }}, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}!</h2>
                <p>
                    @if ($k['overdue_followups'] > 0)
                        You have <strong>{{ $k['overdue_followups'] }}</strong> overdue follow-up{{ $k['overdue_followups'] === 1 ? '' : 's' }}
                        and <strong>{{ $k['open_leads'] }}</strong> open lead{{ $k['open_leads'] === 1 ? '' : 's' }} in your pipeline.
                    @else
                        You're all caught up on follow-ups. {{ $k['open_leads'] }} lead{{ $k['open_leads'] === 1 ? '' : 's' }} are open in your pipeline.
                    @endif
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="{{ route('leads.create') }}" class="btn btn-light"><i class="bi bi-plus-lg"></i> Add a lead</a>
            </div>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('leads.index') }}" class="kpi">
                <span class="kpi-icon tone-primary"><i class="bi bi-funnel"></i></span>
                <div class="kpi-label">Total leads</div>
                <div class="kpi-value tabular">{{ number_format($k['total_leads']) }}</div>
                <div class="kpi-meta"><i class="bi bi-plus-circle"></i> {{ number_format($k['new_this_month']) }} added this month</div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('leads.index', ['status' => 'in_progress']) }}" class="kpi">
                <span class="kpi-icon tone-warning"><i class="bi bi-hourglass-split"></i></span>
                <div class="kpi-label">Open pipeline</div>
                <div class="kpi-value tabular">{{ number_format($k['open_leads']) }}</div>
                <div class="kpi-meta {{ $k['overdue_followups'] ? 'overdue' : '' }}">
                    <i class="bi bi-alarm"></i> {{ $k['overdue_followups'] }} overdue follow-up{{ $k['overdue_followups'] === 1 ? '' : 's' }}
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('customers.index') }}" class="kpi">
                <span class="kpi-icon tone-success"><i class="bi bi-person-check"></i></span>
                <div class="kpi-label">Customers</div>
                <div class="kpi-value tabular">{{ number_format($k['customers']) }}</div>
                <div class="kpi-meta"><i class="bi bi-arrow-repeat"></i> {{ $trendConverted }} converted in last 30 days</div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('leads.index', ['status' => 'won']) }}" class="kpi">
                <span class="kpi-icon tone-info"><i class="bi bi-trophy"></i></span>
                <div class="kpi-label">Win rate</div>
                <div class="kpi-value tabular">{{ $k['win_rate'] !== null ? $k['win_rate'].'%' : '—' }}</div>
                <div class="kpi-meta">Won ÷ (won + lost) leads</div>
            </a>
        </div>
    </div>

    {{-- Trend + pipeline --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header flex-wrap">
                    <div>
                        <h2 class="card-title">Lead activity</h2>
                        <div class="card-subtitle">Leads created vs. converted · last {{ \App\Services\DashboardStats::TREND_DAYS }} days</div>
                    </div>
                    <div class="legend" aria-hidden="true">
                        <span><i style="background: var(--chart-1)"></i> Created <strong class="text-body tabular">{{ $trendCreated }}</strong></span>
                        <span><i style="background: var(--chart-2)"></i> Converted <strong class="text-body tabular">{{ $trendConverted }}</strong></span>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="chart-box">
                        <canvas id="trendChart" role="img"
                                aria-label="Line chart: {{ $trendCreated }} leads created and {{ $trendConverted }} converted over the last {{ \App\Services\DashboardStats::TREND_DAYS }} days"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Pipeline by status</h2>
                        <div class="card-subtitle">{{ number_format($k['total_leads']) }} leads in total</div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    @foreach ($stats['by_status'] as $row)
                        @php($pct = round($row['value'] / $statusTotal * 100))
                        <a href="{{ route('leads.index', ['status' => $row['key']]) }}" class="d-block text-body mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-medium">{{ $row['label'] }}</span>
                                <span class="small text-muted tabular"><strong class="text-body">{{ $row['value'] }}</strong> · {{ $pct }}%</span>
                            </div>
                            <div class="progress" role="progressbar" aria-label="{{ $row['label'] }}" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width: {{ $pct }}%; background: var(--{{ $statusTones[$row['key']] }})"></div>
                            </div>
                        </a>
                    @endforeach

                    <hr class="my-4">

                    <h3 class="card-title mb-1">Leads by source</h3>
                    <div class="chart-box-sm">
                        <canvas id="sourceChart" role="img"
                                aria-label="Bar chart of leads by source: {{ collect($stats['by_source'])->map(fn ($s) => $s['label'].' '.$s['value'])->implode(', ') }}"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Follow-ups --}}
        <div class="{{ $stats['team']->isNotEmpty() ? 'col-xl-7' : 'col-xl-8' }}">
            <div class="card table-card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Upcoming follow-ups</h2>
                        <div class="card-subtitle">Open leads due within 7 days, including overdue</div>
                    </div>
                    <a href="{{ route('leads.index', ['sort' => 'follow_up_date', 'direction' => 'asc']) }}" class="btn btn-sm btn-light-soft">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                        <tr><th>Lead</th><th>Status</th><th>Owner</th><th class="text-end">Due</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($stats['upcoming_followups'] as $lead)
                            @php($overdue = $lead->follow_up_date->lt(today()))
                            <tr>
                                <td>
                                    <a href="{{ route('leads.show', $lead) }}" class="identity">
                                        <x-avatar :name="$lead->name" size="sm" />
                                        <span class="min-w-0">
                                            <span class="title d-block text-truncate">{{ $lead->name }}</span>
                                            <span class="sub d-block text-truncate">{{ $lead->company ?? $lead->email }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td><x-status-badge :status="$lead->status" /></td>
                                <td class="text-2">{{ $lead->assignee?->name ?? 'Unassigned' }}</td>
                                <td class="text-end nowrap">
                                    <span class="{{ $overdue ? 'overdue' : ($lead->follow_up_date->isToday() ? 'due-today' : '') }}">
                                        @if ($overdue) <i class="bi bi-exclamation-circle"></i> @endif
                                        {{ $lead->follow_up_date->isToday() ? 'Today' : $lead->follow_up_date->format('d M') }}
                                    </span>
                                    <div class="small text-muted">{{ $lead->follow_up_date->isToday() ? '' : $lead->follow_up_date->diffForHumans(today()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state icon="bi-calendar-check" title="No follow-ups due" message="Nothing scheduled for the next 7 days." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($stats['team']->isNotEmpty())
            {{-- Team performance (admin only) --}}
            <div class="col-xl-5">
                <div class="card table-card h-100">
                    <div class="card-header">
                        <div>
                            <h2 class="card-title">Team performance</h2>
                            <div class="card-subtitle">Sales users ranked by won leads</div>
                        </div>
                        <a href="{{ route('users.index') }}" class="btn btn-sm btn-light-soft">Manage</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Member</th><th class="text-end">Open</th><th class="text-end">Won</th><th style="width: 32%">Win rate</th></tr></thead>
                            <tbody>
                            @foreach ($stats['team'] as $member)
                                <tr>
                                    <td>
                                        <a href="{{ route('leads.index', ['assigned_to' => $member->id]) }}" class="identity">
                                            <x-avatar :name="$member->name" size="sm" />
                                            <span class="min-w-0">
                                                <span class="title d-block text-truncate">{{ $member->name }}</span>
                                                <span class="sub d-block">{{ $member->total_count }} leads</span>
                                            </span>
                                        </a>
                                    </td>
                                    <td class="text-end tabular">{{ $member->open_count }}</td>
                                    <td class="text-end tabular fw-semibold">{{ $member->won_count }}</td>
                                    <td>
                                        @if ($member->win_rate !== null)
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-fill"><div class="progress-bar" style="width: {{ $member->win_rate }}%"></div></div>
                                                <span class="small tabular text-2">{{ $member->win_rate }}%</span>
                                            </div>
                                        @else
                                            <span class="text-muted small">No closed leads</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        {{-- Recent conversions --}}
        <div class="{{ $stats['team']->isNotEmpty() ? 'col-12' : 'col-xl-4' }}">
            <div class="card h-100">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Recent conversions</h2>
                        <div class="card-subtitle">Leads that became customers</div>
                    </div>
                </div>
                @if ($stats['recent_conversions']->isEmpty())
                    <x-empty-state icon="bi-trophy" title="No conversions yet" message="Mark a lead as Won to convert it." />
                @else
                    <div class="{{ $stats['team']->isNotEmpty() ? 'row g-0' : '' }}">
                        @foreach ($stats['recent_conversions'] as $lead)
                            <div class="{{ $stats['team']->isNotEmpty() ? 'col-md-6 col-xl-4' : '' }}">
                                <a href="{{ route('leads.show', $lead) }}" class="list-row text-body">
                                    <span class="avatar avatar-sm tone-success" style="background: var(--success-soft); color: var(--success)"><i class="bi bi-check2"></i></span>
                                    <span class="min-w-0 flex-fill">
                                        <span class="d-block fw-semibold text-truncate">{{ $lead->customer?->name ?? $lead->name }}</span>
                                        <span class="d-block small text-muted text-truncate">{{ $lead->customer?->company ?? $lead->email }} · {{ $lead->assignee?->name ?? 'Unassigned' }}</span>
                                    </span>
                                    <span class="small text-muted nowrap">{{ $lead->converted_at->diffForHumans(short: true) }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            if (!window.Chart) return;

            var trend = @json($stats['trend']);
            var sources = @json($stats['by_source']);
            var charts = [];

            function css(name) {
                return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            }

            function build() {
                charts.forEach(function (c) { c.destroy(); });
                charts = [];

                var c1 = css('--chart-1'), c2 = css('--chart-2'), grid = css('--chart-grid'),
                    axis = css('--chart-axis'), surface = css('--surface'), text = css('--text');

                Chart.defaults.font.family = css('--font-sans');
                Chart.defaults.font.size = 11;
                Chart.defaults.color = axis;

                var tooltip = {
                    backgroundColor: surface, titleColor: text, bodyColor: text,
                    borderColor: grid, borderWidth: 1, padding: 10, cornerRadius: 8,
                    boxPadding: 4, usePointStyle: true
                };

                var ctx = document.getElementById('trendChart');
                var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 290);
                gradient.addColorStop(0, c1 + '33');
                gradient.addColorStop(1, c1 + '00');

                charts.push(new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: trend.labels,
                        datasets: [
                            { label: 'Created', data: trend.created, borderColor: c1, backgroundColor: gradient, fill: true,
                              borderWidth: 2, tension: .35, pointRadius: 0, pointHoverRadius: 5,
                              pointHoverBackgroundColor: c1, pointHoverBorderColor: surface, pointHoverBorderWidth: 2 },
                            { label: 'Converted', data: trend.converted, borderColor: c2, backgroundColor: c2, fill: false,
                              borderWidth: 2, tension: .35, pointRadius: 0, pointHoverRadius: 5,
                              pointHoverBackgroundColor: c2, pointHoverBorderColor: surface, pointHoverBorderWidth: 2 }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: false }, tooltip: tooltip },
                        scales: {
                            x: { grid: { display: false }, border: { color: grid }, ticks: { maxTicksLimit: 8, maxRotation: 0 } },
                            y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 5 } }
                        }
                    }
                }));

                charts.push(new Chart(document.getElementById('sourceChart'), {
                    type: 'bar',
                    data: {
                        labels: sources.map(function (s) { return s.label; }),
                        datasets: [{ label: 'Leads', data: sources.map(function (s) { return s.value; }),
                                     backgroundColor: c1, hoverBackgroundColor: css('--primary-hover'),
                                     borderRadius: 4, borderSkipped: 'start', maxBarThickness: 36 }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: tooltip },
                        scales: {
                            x: { grid: { display: false }, border: { color: grid } },
                            y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { precision: 0, maxTicksLimit: 4 } }
                        }
                    }
                }));
            }

            document.addEventListener('DOMContentLoaded', build);
            document.addEventListener('crm:themechange', function () { if (charts.length) build(); });
        })();
    </script>
@endpush
