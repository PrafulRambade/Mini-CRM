@extends('layouts.app')

@section('title', 'Users & Roles')

@section('content')
    <x-page-header title="Users & roles" subtitle="Manage who can access the CRM and what they can do."
                   :breadcrumbs="['Administration' => null, 'Users' => null]">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add user</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Filters, sorting and paging reload this region over AJAX (see public/js/app.js). --}}
    <div data-ajax-listing data-endpoint="{{ route('users.table') }}" aria-live="polite">
        @include('users._listing')
    </div>
@endsection
