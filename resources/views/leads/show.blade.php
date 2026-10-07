@extends('layouts.app')

@section('title', $lead->name)

@php
    $open = in_array($lead->status, [\App\Enums\LeadStatus::New, \App\Enums\LeadStatus::InProgress], true);
    $overdue = $open && $lead->follow_up_date?->lt(today());
@endphp

@section('content')
    <x-page-header :title="$lead->name" :breadcrumbs="['Leads' => route('leads.index'), $lead->name => null]">
        <x-slot:actions>
            @can('convert', $lead)
                <form method="POST" action="{{ route('leads.convert', $lead) }}"
                      data-confirm="The lead will be marked as Won and a customer record will be created (or linked if one with this email already exists)."
                      data-confirm-title="Convert to customer?" data-confirm-variant="success"
                      data-confirm-icon="bi-person-check" data-confirm-button="Convert">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="bi bi-person-check"></i> Convert to customer</button>
                </form>
            @endcan
            @can('update', $lead)
                <a href="{{ route('leads.edit', $lead) }}" class="btn btn-light-soft"><i class="bi bi-pencil"></i> Edit</a>
            @endcan
            @can('delete', $lead)
                <form method="POST" action="{{ route('leads.destroy', $lead) }}"
                      data-confirm="“{{ $lead->name }}” will be removed from the pipeline." data-confirm-title="Delete this lead?"
                      data-confirm-button="Delete" data-confirm-icon="bi-trash">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-soft-danger" data-no-loading><i class="bi bi-trash"></i> Delete</button>
                </form>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="row g-4">
        {{-- Profile card --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="profile-cover"></div>
                <div class="card-body pt-0 text-center profile-head">
                    <x-avatar :name="$lead->name" size="xl" />
                    <h2 class="h5 mt-3 mb-1">{{ $lead->name }}</h2>
                    <div class="text-muted mb-3">{{ $lead->company ?? 'No company' }}</div>
                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <x-status-badge :status="$lead->status" />
                        <span class="chip">{{ $lead->source->label() }}</span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="mailto:{{ $lead->email }}" class="btn btn-soft-primary flex-fill"><i class="bi bi-envelope"></i> Email</a>
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->phone) }}" class="btn btn-light-soft flex-fill"><i class="bi bi-telephone"></i> Call</a>
                    </div>
                </div>
            </div>

            <div class="card {{ $lead->customer ? 'border-success' : '' }}">
                <div class="card-header">
                    <h3 class="card-title">Customer</h3>
                    @if ($lead->customer)
                        <span class="pill pill-won">Converted</span>
                    @endif
                </div>
                <div class="card-body pt-0">
                    @if ($lead->customer)
                        <div class="identity mb-3">
                            <span class="avatar tone-success" style="background: var(--success-soft); color: var(--success)"><i class="bi bi-person-check"></i></span>
                            <div class="min-w-0">
                                <div class="title text-truncate">{{ $lead->customer->name }}</div>
                                <div class="sub">Customer #{{ $lead->customer->id }}</div>
                            </div>
                        </div>
                        <div class="small text-2 mb-1"><i class="bi bi-envelope me-2 text-muted"></i>{{ $lead->customer->email }}</div>
                        <div class="small text-2 mb-1"><i class="bi bi-telephone me-2 text-muted"></i>{{ $lead->customer->phone }}</div>
                        @if ($lead->customer->company)
                            <div class="small text-2 mb-3"><i class="bi bi-building me-2 text-muted"></i>{{ $lead->customer->company }}</div>
                        @endif
                        <a href="{{ route('customers.index', ['search' => $lead->customer->email]) }}" class="btn btn-sm btn-soft-success w-100 mt-2">
                            View in customers <i class="bi bi-arrow-right"></i>
                        </a>
                    @else
                        <div class="text-center py-3">
                            <div class="empty-icon mx-auto mb-3" style="width:52px;height:52px;display:grid;place-items:center;border-radius:14px;background:var(--surface-2);border:1px solid var(--border);color:var(--muted)">
                                <i class="bi bi-hourglass-split fs-5"></i>
                            </div>
                            <p class="text-muted small mb-0">Not converted yet. Mark the lead as <strong class="text-body">Won</strong> to create a customer.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Details --}}
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">Lead details</h3></div>
                <div class="card-body pt-0">
                    <div class="detail-list">
                        <div class="detail-item"><div class="label">Email</div><div class="value"><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></div></div>
                        <div class="detail-item"><div class="label">Phone</div><div class="value tabular">{{ $lead->phone }}</div></div>
                        <div class="detail-item"><div class="label">Company</div><div class="value">{{ $lead->company ?? '—' }}</div></div>
                        <div class="detail-item"><div class="label">Source</div><div class="value">{{ $lead->source->label() }}</div></div>
                        <div class="detail-item">
                            <div class="label">Owner</div>
                            <div class="value">
                                @if ($lead->assignee)
                                    <span class="d-inline-flex align-items-center gap-2"><x-avatar :name="$lead->assignee->name" size="sm" /> {{ $lead->assignee->name }}</span>
                                @else
                                    <span class="text-muted">Unassigned</span>
                                @endif
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="label">Follow-up</div>
                            <div class="value {{ $overdue ? 'overdue' : '' }}">
                                @if ($lead->follow_up_date)
                                    {{ $lead->follow_up_date->format('D, d M Y') }}
                                    @if ($overdue) <span class="pill pill-lost no-dot ms-1">Overdue</span>
                                    @elseif ($open && $lead->follow_up_date->isToday()) <span class="pill pill-in_progress no-dot ms-1">Today</span>
                                    @endif
                                @else
                                    <span class="text-muted">Not scheduled</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title">Notes</h3></div>
                <div class="card-body pt-0">
                    @if ($lead->notes)
                        <p class="mb-0 text-2" style="white-space: pre-line">{{ $lead->notes }}</p>
                    @else
                        <p class="mb-0 text-muted">No notes yet.</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Activity</h3></div>
                <div class="card-body pt-0">
                    <ul class="timeline">
                        @if ($lead->converted_at)
                            <li>
                                <span class="dot tone-success"><i class="bi bi-trophy"></i></span>
                                <div class="fw-semibold">Converted to customer</div>
                                <div class="when">{{ $lead->converted_at->format('d M Y, H:i') }} · {{ $lead->converted_at->diffForHumans() }}</div>
                            </li>
                        @endif
                        @if ($lead->updated_at && $lead->updated_at->ne($lead->created_at) && (! $lead->converted_at || $lead->updated_at->ne($lead->converted_at)))
                            <li>
                                <span class="dot tone-primary"><i class="bi bi-pencil"></i></span>
                                <div class="fw-semibold">Last updated</div>
                                <div class="when">{{ $lead->updated_at->format('d M Y, H:i') }} · {{ $lead->updated_at->diffForHumans() }}</div>
                            </li>
                        @endif
                        <li>
                            <span class="dot tone-neutral"><i class="bi bi-plus-lg"></i></span>
                            <div class="fw-semibold">Lead created @if ($lead->creator) <span class="fw-normal text-muted">by {{ $lead->creator->name }}</span> @endif</div>
                            <div class="when">{{ $lead->created_at->format('d M Y, H:i') }} · {{ $lead->created_at->diffForHumans() }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
