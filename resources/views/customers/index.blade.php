@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <x-page-header title="Customers"
                   :subtitle="auth()->user()->isAdmin() ? 'Everyone who converted from a won lead.' : 'Customers converted from your leads.'"
                   :breadcrumbs="['Customers' => null]" />

    {{-- Filters, sorting and paging reload this region over AJAX (see public/js/app.js). --}}
    <div data-ajax-listing data-endpoint="{{ route('customers.table') }}" aria-live="polite">
        @include('customers._listing')
    </div>
@endsection
