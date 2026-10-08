@php($authUser = auth()->user())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body>
<a href="#main-content" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-primary" style="z-index:2000">Skip to content</a>

<div class="app-shell">
    {{-- ============ Sidebar ============ --}}
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <span class="brand-logo"><i class="bi bi-graph-up-arrow"></i></span>
            <span class="brand-text">{{ config('app.name') }}<small>Sales Workspace</small></span>
        </a>

        <nav class="sidebar-nav">
            <p class="nav-heading">Overview</p>
            <a href="{{ route('dashboard') }}" class="sidebar-link @if(request()->routeIs('dashboard')) active @endif"
               data-sidebar-tip title="Dashboard">
                <i class="bi bi-grid-1x2"></i><span class="link-text">Dashboard</span>
            </a>

            <div class="nav-divider"></div>
            <p class="nav-heading">CRM</p>
            <a href="{{ route('leads.index') }}" class="sidebar-link @if(request()->routeIs('leads.*') && ! request()->routeIs('leads.create')) active @endif"
               data-sidebar-tip title="Leads">
                <i class="bi bi-funnel"></i><span class="link-text">Leads</span>
                @if (($navCounts['open_leads'] ?? 0) > 0)
                    <span class="badge-count" title="Open leads">{{ $navCounts['open_leads'] }}</span>
                @endif
            </a>
            <a href="{{ route('customers.index') }}" class="sidebar-link @if(request()->routeIs('customers.*')) active @endif"
               data-sidebar-tip title="Customers">
                <i class="bi bi-person-badge"></i><span class="link-text">Customers</span>
            </a>
            @can('create', App\Models\Lead::class)
                <a href="{{ route('leads.create') }}" class="sidebar-link @if(request()->routeIs('leads.create')) active @endif"
                   data-sidebar-tip title="Add Lead">
                    <i class="bi bi-plus-circle"></i><span class="link-text">Add Lead</span>
                </a>
            @endcan

            @can('viewAny', App\Models\User::class)
                <div class="nav-divider"></div>
                <p class="nav-heading">Administration</p>
                <a href="{{ route('users.index') }}" class="sidebar-link @if(request()->routeIs('users.*')) active @endif"
                   data-sidebar-tip title="Users">
                    <i class="bi bi-people"></i><span class="link-text">Users &amp; Roles</span>
                </a>
            @endcan
            @can('viewActivityLog')
                <a href="{{ route('activity.index') }}" class="sidebar-link @if(request()->routeIs('activity.*')) active @endif"
                   data-sidebar-tip title="Activity Log">
                    <i class="bi bi-journal-text"></i><span class="link-text">Activity Log</span>
                </a>
            @endcan

            <div class="nav-divider"></div>
            <p class="nav-heading">Account</p>
            <a href="{{ route('profile.edit') }}" class="sidebar-link @if(request()->routeIs('profile.*')) active @endif"
               data-sidebar-tip title="My Profile">
                <i class="bi bi-person-gear"></i><span class="link-text">My Profile</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <x-avatar :name="$authUser->name" size="sm" />
                <div class="min-w-0 flex-fill">
                    <div class="name text-truncate">{{ $authUser->name }}</div>
                    <div class="role">{{ $authUser->role->label() }}</div>
                </div>
            </div>
        </div>
    </aside>
    <div class="sidebar-backdrop"></div>

    {{-- ============ Main ============ --}}
    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn" data-action="toggle-sidebar" aria-label="Toggle sidebar" aria-controls="sidebar">
                <i class="bi bi-layout-sidebar"></i>
            </button>

            <form method="GET" action="{{ route('leads.index') }}" class="topbar-search d-none d-md-block" role="search">
                <i class="bi bi-search"></i>
                <input type="search" id="globalSearch" name="search" class="form-control" maxlength="100"
                       placeholder="Search leads…" aria-label="Search leads" value="{{ request()->routeIs('leads.index') && is_string(request()->query('search')) ? request()->query('search') : '' }}">
                <kbd>/</kbd>
            </form>

            <div class="ms-auto d-flex align-items-center gap-2">
                @can('create', App\Models\Lead::class)
                    <a href="{{ route('leads.create') }}" class="btn btn-primary d-none d-sm-inline-flex">
                        <i class="bi bi-plus-lg"></i> New Lead
                    </a>
                @endcan

                <button type="button" class="icon-btn" data-action="toggle-theme" aria-label="Toggle dark mode"
                        data-bs-toggle="tooltip" data-bs-placement="bottom" title="Toggle theme">
                    <i class="bi bi-moon-stars" data-theme-icon></i>
                </button>

                <div class="dropdown">
                    <button class="user-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                        <x-avatar :name="$authUser->name" size="sm" />
                        <span class="d-none d-lg-block text-start lh-sm">
                            <span class="d-block fw-semibold small">{{ $authUser->name }}</span>
                            <span class="d-block text-muted" style="font-size:.7rem">{{ $authUser->role->label() }}</span>
                        </span>
                        <i class="bi bi-chevron-down small text-muted d-none d-lg-block"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width: 240px">
                        <li class="dropdown-header">
                            <div class="fw-semibold text-body">{{ $authUser->name }}</div>
                            <div class="small text-muted text-truncate">{{ $authUser->email }}</div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person"></i> My Profile</a></li>
                        <li><a class="dropdown-item" href="{{ route('leads.index') }}"><i class="bi bi-funnel"></i> My Leads</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger" data-no-loading><i class="bi bi-box-arrow-right text-danger"></i> Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="content" id="main-content">
            @yield('content')
        </main>

        <footer class="app-footer">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</span>
            <span>Signed in as {{ $authUser->email }}</span>
        </footer>
    </div>
</div>

{{-- Shared confirmation dialog (used by any form with data-confirm) --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div data-confirm-icon class="modal-icon mx-auto tone-danger"><i class="bi bi-exclamation-triangle"></i></div>
                <h5 class="mb-2" id="confirmModalTitle" data-confirm-title>Are you sure?</h5>
                <p class="text-2 mb-4" data-confirm-message></p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light-soft flex-fill" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger flex-fill" data-confirm-ok>Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>

@include('partials.flash')
@include('partials.scripts')
</body>
</html>
