@extends('layouts.app')

@section('title', 'New Lead')

@section('content')
    <form method="POST" action="{{ route('leads.store') }}" novalidate>
        @csrf

        <x-page-header title="New lead" subtitle="Capture a new opportunity in your pipeline."
                       :breadcrumbs="['Leads' => route('leads.index'), 'New' => null]">
            <x-slot:actions>
                <a href="{{ route('leads.index') }}" class="btn btn-light-soft">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Create lead</button>
            </x-slot:actions>
        </x-page-header>

        @include('leads._form')
    </form>
@endsection
