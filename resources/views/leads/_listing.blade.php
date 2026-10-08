@php
    $isAdmin = auth()->user()->isAdmin();
    $activeFilters = collect([
        'search' => $filters['search'] ? '“'.$filters['search'].'”' : null,
        'status' => $filters['status'] ? \App\Enums\LeadStatus::from($filters['status'])->label() : null,
        'source' => $filters['source'] ? \App\Enums\LeadSource::from($filters['source'])->label() : null,
        'assigned_to' => $filters['assigned_to'] ? ($assignees->firstWhere('id', $filters['assigned_to'])?->name ?? '#'.$filters['assigned_to']) : null,
    ])->filter();
@endphp

{{-- Status quick tabs --}}
<ul class="nav nav-pills gap-1 mb-3 flex-nowrap overflow-auto" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ ! $filters['status'] ? 'active' : '' }} py-1 px-3 small fw-semibold"
           href="{{ \App\Support\Listing::url(['status' => null, 'page' => null]) }}">All</a>
    </li>
    @foreach ($statuses as $status)
        <li class="nav-item">
            <a class="nav-link {{ $filters['status'] === $status->value ? 'active' : '' }} py-1 px-3 small fw-semibold text-nowrap"
               href="{{ \App\Support\Listing::url(['status' => $status->value, 'page' => null]) }}">{{ $status->label() }}</a>
        </li>
    @endforeach
</ul>

<div class="card table-card">
    <form method="GET" action="{{ route('leads.index') }}" class="filter-bar" role="search">
        <div class="search input-icon">
            <i class="bi bi-search"></i>
            <input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100"
                   class="form-control" placeholder="Search name, email, phone, company…" aria-label="Search leads">
        </div>
        <input type="hidden" name="status" value="{{ $filters['status'] }}">
        <select name="source" class="form-select" aria-label="Filter by source" data-autosubmit>
            <option value="">All sources</option>
            @foreach ($sources as $source)
                <option value="{{ $source->value }}" @selected($filters['source'] === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </select>
        @if ($isAdmin)
            <select name="assigned_to" class="form-select" aria-label="Filter by owner" data-autosubmit>
                <option value="">All owners</option>
                @foreach ($assignees as $assignee)
                    <option value="{{ $assignee->id }}" @selected($filters['assigned_to'] === $assignee->id)>{{ $assignee->name }}</option>
                @endforeach
            </select>
        @endif
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <button type="submit" class="btn btn-light-soft" data-no-loading><i class="bi bi-funnel"></i> Apply</button>
        @if ($activeFilters->isNotEmpty())
            <a href="{{ route('leads.index') }}" class="btn btn-ghost"><i class="bi bi-x-lg"></i> Clear</a>
        @endif
    </form>

    @if ($activeFilters->isNotEmpty())
        <div class="active-filters pb-2">
            <span class="small text-muted me-1">Filtered by:</span>
            @foreach ($activeFilters as $key => $label)
                <a href="{{ \App\Support\Listing::url([$key => null, 'page' => null]) }}" class="chip" title="Remove filter">
                    {{ $label }} <i class="bi bi-x"></i>
                </a>
            @endforeach
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th><x-sort-link column="name" label="Lead" :sort="$sort" :direction="$direction" /></th>
                <th>Phone</th>
                <th><x-sort-link column="company" label="Company" :sort="$sort" :direction="$direction" /></th>
                <th><x-sort-link column="source" label="Source" :sort="$sort" :direction="$direction" /></th>
                <th><x-sort-link column="status" label="Status" :sort="$sort" :direction="$direction" /></th>
                @if ($isAdmin)<th>Owner</th>@endif
                <th><x-sort-link column="follow_up_date" label="Follow-up" :sort="$sort" :direction="$direction" /></th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td>
                        <a href="{{ route('leads.show', $lead) }}" class="identity">
                            <x-avatar :name="$lead->name" />
                            <span class="min-w-0">
                                <span class="title d-block text-truncate" style="max-width: 220px">{{ $lead->name }}</span>
                                <span class="sub d-block text-truncate" style="max-width: 220px">{{ $lead->email }}</span>
                            </span>
                        </a>
                    </td>
                    <td class="nowrap text-2 tabular">{{ $lead->phone }}</td>
                    <td class="text-2">{{ $lead->company ?? '—' }}</td>
                    <td><span class="chip">{{ $lead->source->label() }}</span></td>
                    <td>
                        <x-status-badge :status="$lead->status" />
                        @if ($lead->customer)
                            <i class="bi bi-patch-check-fill text-success ms-1" data-bs-toggle="tooltip" title="Converted to customer #{{ $lead->customer->id }}"></i>
                        @endif
                    </td>
                    @if ($isAdmin)
                        <td>
                            @if ($lead->assignee)
                                <span class="d-inline-flex align-items-center gap-2 text-2">
                                    <x-avatar :name="$lead->assignee->name" size="sm" /> {{ $lead->assignee->name }}
                                </span>
                            @else
                                <span class="text-muted">Unassigned</span>
                            @endif
                        </td>
                    @endif
                    <td class="nowrap">
                        @if ($lead->follow_up_date)
                            @php($open = in_array($lead->status, [\App\Enums\LeadStatus::New, \App\Enums\LeadStatus::InProgress], true))
                            <span class="{{ $open && $lead->follow_up_date->lt(today()) ? 'overdue' : ($open && $lead->follow_up_date->isToday() ? 'due-today' : 'text-2') }}">
                                {{ $lead->follow_up_date->format('d M Y') }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end nowrap">
                        <a href="{{ route('leads.show', $lead) }}" class="btn btn-ghost btn-icon" data-bs-toggle="tooltip" title="View" aria-label="View {{ $lead->name }}"><i class="bi bi-eye"></i></a>
                        @can('update', $lead)
                            <a href="{{ route('leads.edit', $lead) }}" class="btn btn-ghost btn-icon" data-bs-toggle="tooltip" title="Edit" aria-label="Edit {{ $lead->name }}"><i class="bi bi-pencil"></i></a>
                        @endcan
                        @can('delete', $lead)
                            <form method="POST" action="{{ route('leads.destroy', $lead) }}" class="d-inline"
                                  data-confirm="“{{ $lead->name }}” will be removed from the pipeline."
                                  data-confirm-title="Delete this lead?" data-confirm-button="Delete" data-confirm-icon="bi-trash">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-icon text-danger" data-no-loading aria-label="Delete {{ $lead->name }}"><i class="bi bi-trash"></i></button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 8 : 7 }}">
                        <x-empty-state icon="bi-funnel" title="No leads found"
                                       :message="$activeFilters->isNotEmpty() ? 'Try adjusting or clearing your filters.' : 'Create your first lead to start building the pipeline.'">
                            @if ($activeFilters->isNotEmpty())
                                <a href="{{ route('leads.index') }}" class="btn btn-light-soft btn-sm">Clear filters</a>
                            @else
                                <a href="{{ route('leads.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Lead</a>
                            @endif
                        </x-empty-state>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-pager :paginator="$leads" label="leads" />
</div>
