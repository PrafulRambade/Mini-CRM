@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <form method="POST" action="{{ route('users.update', $user) }}" novalidate>
        @csrf
        @method('PUT')

        <x-page-header title="Edit user" :subtitle="$user->email"
                       :breadcrumbs="['Users' => route('users.index'), $user->name => null]">
            <x-slot:actions>
                <a href="{{ route('users.index') }}" class="btn btn-light-soft">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Save changes</button>
            </x-slot:actions>
        </x-page-header>

        @include('users._form')
    </form>
@endsection
