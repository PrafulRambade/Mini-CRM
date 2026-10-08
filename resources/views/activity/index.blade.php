@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
    <x-page-header title="Activity log"
                   :subtitle="'Audit trail of sign-ins, user changes and lead actions. Entries are kept for '.$retentionDays.' days and cannot be edited.'"
                   :breadcrumbs="['Administration' => null, 'Activity log' => null]" />

    {{-- Filters, sorting and paging reload this region over AJAX (see public/js/app.js). --}}
    <div data-ajax-listing data-endpoint="{{ route('activity.table') }}" aria-live="polite">
        @include('activity._listing')
    </div>
@endsection
