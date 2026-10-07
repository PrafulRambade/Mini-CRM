@extends('layouts.app')

@section('title', 'Edit Lead')

@section('content')
    <form method="POST" action="{{ route('leads.update', $lead) }}" novalidate>
        @csrf
        @method('PUT')

        <x-page-header title="Edit lead" :subtitle="$lead->name"
                       :breadcrumbs="['Leads' => route('leads.index'), $lead->name => route('leads.show', $lead), 'Edit' => null]">
            <x-slot:actions>
                <a href="{{ route('leads.show', $lead) }}" class="btn btn-light-soft">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Save changes</button>
            </x-slot:actions>
        </x-page-header>

        @if ($lead->isConverted())
            <div class="alert d-flex align-items-center gap-2 tone-success border-0 mb-4">
                <i class="bi bi-patch-check-fill"></i>
                This lead was converted to a customer on {{ $lead->converted_at?->format('d M Y') }}. Its status is locked; other details can still be updated.
            </div>
        @endif

        @include('leads._form')
    </form>
@endsection
