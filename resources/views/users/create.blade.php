@extends('layouts.app')

@section('title', 'Add User')

@section('content')
    <form method="POST" action="{{ route('users.store') }}" novalidate>
        @csrf

        <x-page-header title="Add user" subtitle="Invite a teammate to the CRM."
                       :breadcrumbs="['Users' => route('users.index'), 'Add' => null]">
            <x-slot:actions>
                <a href="{{ route('users.index') }}" class="btn btn-light-soft">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Create user</button>
            </x-slot:actions>
        </x-page-header>

        @include('users._form')
    </form>
@endsection
