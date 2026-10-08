<div class="card table-card">
    <form method="GET" action="{{ route('customers.index') }}" class="filter-bar" role="search">
        <div class="search input-icon">
            <i class="bi bi-search"></i>
            <input type="search" name="search" value="{{ $search }}" maxlength="100"
                   class="form-control" placeholder="Search name, email, phone, company…" aria-label="Search customers">
        </div>
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <button type="submit" class="btn btn-light-soft" data-no-loading><i class="bi bi-search"></i> Search</button>
        @if ($search)
            <a href="{{ route('customers.index') }}" class="btn btn-ghost"><i class="bi bi-x-lg"></i> Clear</a>
        @endif
        <span class="ms-auto small text-muted"><i class="bi bi-info-circle"></i> Customers are created automatically when a lead is won.</span>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th><x-sort-link column="name" label="Customer" :sort="$sort" :direction="$direction" /></th>
                <th>Phone</th>
                <th><x-sort-link column="company" label="Company" :sort="$sort" :direction="$direction" /></th>
                <th>Converted from</th>
                <th class="text-end"><x-sort-link column="created_at" label="Customer since" :sort="$sort" :direction="$direction" /></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td>
                        <div class="identity">
                            <x-avatar :name="$customer->name" />
                            <span class="min-w-0">
                                <span class="title d-block text-truncate" style="max-width: 240px">{{ $customer->name }}</span>
                                <a href="mailto:{{ $customer->email }}" class="sub d-block text-truncate" style="max-width: 240px">{{ $customer->email }}</a>
                            </span>
                        </div>
                    </td>
                    <td class="nowrap text-2 tabular">{{ $customer->phone }}</td>
                    <td class="text-2">{{ $customer->company ?? '—' }}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse ($customer->leads as $lead)
                                <a href="{{ route('leads.show', $lead->id) }}" class="chip"><i class="bi bi-funnel"></i> Lead #{{ $lead->id }}</a>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="text-end nowrap">
                        <div>{{ $customer->created_at->format('d M Y') }}</div>
                        <div class="small text-muted">{{ $customer->created_at->diffForHumans() }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <x-empty-state icon="bi-person-badge" :title="$search ? 'No matching customers' : 'No customers yet'"
                                       :message="$search ? 'Try a different search term.' : 'Customers appear here once a lead is marked as Won.'" />
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <x-pager :paginator="$customers" label="customers" />
</div>
