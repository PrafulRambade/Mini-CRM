@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
    <x-page-header title="Activity log"
                   :subtitle="'Audit trail of sign-ins, user changes and lead actions. Entries are kept for '.$retentionDays.' days and cannot be edited.'"
                   :breadcrumbs="['Administration' => null, 'Activity log' => null]" />

    <ul class="nav nav-pills gap-1 mb-3 flex-nowrap overflow-auto">
        <li class="nav-item">
            <a class="nav-link {{ ! $group ? 'active' : '' }} py-1 px-3 small fw-semibold" href="{{ request()->fullUrlWithQuery(['group' => null, 'page' => null]) }}">All</a>
        </li>
        @foreach ($groups as $key => $def)
            <li class="nav-item">
                <a class="nav-link {{ $group === $key ? 'active' : '' }} py-1 px-3 small fw-semibold text-nowrap"
                   href="{{ request()->fullUrlWithQuery(['group' => $key, 'page' => null]) }}">
                    @if ($key === 'security')<i class="bi bi-shield-exclamation"></i>@endif {{ $def['label'] }}
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card table-card">
        <form method="GET" action="{{ route('activity.index') }}" class="filter-bar" role="search">
            <div class="search input-icon">
                <i class="bi bi-search"></i>
                <input type="search" name="search" value="{{ $search }}" maxlength="100" class="form-control"
                       placeholder="Search description, event or IP…" aria-label="Search activity">
            </div>
            <input type="hidden" name="group" value="{{ $group }}">
            <button type="submit" class="btn btn-light-soft" data-no-loading><i class="bi bi-search"></i> Search</button>
            @if ($search || $group)
                <a href="{{ route('activity.index') }}" class="btn btn-ghost"><i class="bi bi-x-lg"></i> Clear</a>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr><th>Event</th><th>By</th><th>Details</th><th>IP address</th><th class="text-end">When</th></tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    @php([$icon, $tone] = $log->presentation())
                    <tr>
                        <td>
                            <div class="identity">
                                <span class="avatar avatar-sm tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                                <span class="min-w-0">
                                    <span class="title d-block text-truncate" style="max-width: 320px">{{ $log->description }}</span>
                                    <span class="sub d-block"><code class="small">{{ $log->event }}</code></span>
                                </span>
                            </div>
                        </td>
                        <td class="text-2">
                            @if ($log->user)
                                <span class="d-inline-flex align-items-center gap-2"><x-avatar :name="$log->user->name" size="sm" /> {{ $log->user->name }}</span>
                            @else
                                <span class="text-muted">{{ $log->properties['email'] ?? 'System / guest' }}</span>
                            @endif
                        </td>
                        <td class="small text-2">
                            @foreach (collect($log->properties)->except('email') as $key => $value)
                                <span class="chip mb-1">
                                    {{ str_replace('_', ' ', $key) }}:
                                    @if (is_array($value) && array_key_exists('from', $value))
                                        {{ $value['from'] ?? '—' }} → {{ $value['to'] ?? '—' }}
                                    @elseif (is_array($value))
                                        {{ implode(', ', $value) }}
                                    @elseif (is_bool($value))
                                        {{ $value ? 'yes' : 'no' }}
                                    @else
                                        {{ $value ?? '—' }}
                                    @endif
                                </span>
                            @endforeach
                        </td>
                        <td class="small text-muted tabular nowrap">{{ $log->ip_address ?? '—' }}</td>
                        <td class="text-end nowrap">
                            <div title="{{ $log->created_at->toDayDateTimeString() }}">{{ $log->created_at->diffForHumans() }}</div>
                            <div class="small text-muted">{{ $log->created_at->format('d M Y, H:i') }}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state icon="bi-journal-text" title="No activity found" message="Nothing matches these filters yet." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->total() > 0)
            <div class="card-footer-pager">
                <span class="small text-muted">Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ number_format($logs->total()) }} entries</span>
                {{ $logs->onEachSide(1)->links('partials.pagination') }}
            </div>
        @endif
    </div>
@endsection
