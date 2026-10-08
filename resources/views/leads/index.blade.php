@extends('layouts.app')

@section('title', 'Leads')

@section('content')
    <x-page-header title="Leads" :subtitle="auth()->user()->isAdmin() ? 'Every lead across the team.' : 'Leads assigned to you.'" :breadcrumbs="['Leads' => null]">
        <x-slot:actions>
            @can('create', App\Models\Lead::class)
                <a href="{{ route('leads.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Lead</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Filters, sorting and paging reload this region over AJAX (see public/js/app.js). --}}
    <div data-ajax-listing data-endpoint="{{ route('leads.table') }}" aria-live="polite">
        @include('leads._listing')
    </div>
@endsection
