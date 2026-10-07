@extends('layouts.app')

@section('title', 'Users & Roles')

@section('content')
    <x-page-header title="Users & roles" subtitle="Manage who can access the CRM and what they can do."
                   :breadcrumbs="['Administration' => null, 'Users' => null]">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add user</a>
        </x-slot:actions>
    </x-page-header>

    <div class="card table-card">
        <form method="GET" action="{{ route('users.index') }}" class="filter-bar" role="search">
            <div class="search input-icon">
                <i class="bi bi-search"></i>
                <input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100"
                       class="form-control" placeholder="Search name or email…" aria-label="Search users">
            </div>
            <select name="role" class="form-select" aria-label="Filter by role" data-autosubmit>
                <option value="">All roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected($filters['role'] === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select" aria-label="Filter by status" data-autosubmit>
                <option value="">Any status</option>
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
            </select>
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <button type="submit" class="btn btn-light-soft" data-no-loading><i class="bi bi-funnel"></i> Apply</button>
            @if (array_filter($filters))
                <a href="{{ route('users.index') }}" class="btn btn-ghost"><i class="bi bi-x-lg"></i> Clear</a>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th><x-sort-link column="name" label="User" :sort="$sort" :direction="$direction" /></th>
                    <th><x-sort-link column="role" label="Role" :sort="$sort" :direction="$direction" /></th>
                    <th>Status</th>
                    <th class="text-end">Leads</th>
                    <th class="text-end">Won</th>
                    <th><x-sort-link column="created_at" label="Joined" :sort="$sort" :direction="$direction" /></th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    @php($isSelf = auth()->user()->is($user))
                    <tr class="{{ $user->is_active ? '' : 'opacity-75' }}">
                        <td>
                            <div class="identity">
                                <x-avatar :name="$user->name" />
                                <span class="min-w-0">
                                    <span class="title d-block text-truncate">{{ $user->name }} @if ($isSelf)<span class="chip ms-1">You</span>@endif
                                        @if ($user->isProtectedDemoAccount())<span class="chip ms-1" title="Protected demo account"><i class="bi bi-shield-lock"></i> Demo</span>@endif</span>
                                    <span class="sub d-block text-truncate">{{ $user->email }}</span>
                                </span>
                            </div>
                        </td>
                        <td><span class="pill pill-{{ $user->role->value }} no-dot"><i class="bi {{ $user->isAdmin() ? 'bi-shield-check' : 'bi-briefcase' }}"></i> {{ $user->role->label() }}</span></td>
                        <td><span class="pill pill-{{ $user->is_active ? 'active' : 'inactive' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end tabular">
                            <a href="{{ route('leads.index', ['assigned_to' => $user->id]) }}" class="text-body">{{ $user->assigned_leads_count }}</a>
                        </td>
                        <td class="text-end tabular fw-semibold">{{ $user->won_leads_count }}</td>
                        <td class="text-2 nowrap">{{ $user->created_at->format('d M Y') }}</td>
                        <td class="text-end nowrap">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-ghost btn-icon" data-bs-toggle="tooltip" title="Edit" aria-label="Edit {{ $user->name }}"><i class="bi bi-pencil"></i></a>
                            @can('toggleStatus', $user)
                                <form method="POST" action="{{ route('users.toggle-status', $user) }}" class="d-inline"
                                      @if ($user->is_active)
                                          data-confirm="{{ $user->name }} will be signed out everywhere and won't be able to log in until reactivated."
                                          data-confirm-title="Deactivate user?" data-confirm-button="Deactivate" data-confirm-icon="bi-person-slash"
                                      @else
                                          data-confirm="{{ $user->name }} will be able to sign in again." data-confirm-title="Activate user?"
                                          data-confirm-button="Activate" data-confirm-variant="success" data-confirm-icon="bi-person-check"
                                      @endif>
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-ghost btn-icon {{ $user->is_active ? 'text-danger' : 'text-success' }}" data-no-loading
                                            aria-label="{{ $user->is_active ? 'Deactivate' : 'Activate' }} {{ $user->name }}">
                                        <i class="bi {{ $user->is_active ? 'bi-person-slash' : 'bi-person-check' }}"></i>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="bi-people" title="No users found" message="Try adjusting your filters." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <x-pager :paginator="$users" label="users" />
    </div>
@endsection
