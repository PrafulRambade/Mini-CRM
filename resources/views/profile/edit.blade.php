@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <x-page-header title="My profile" subtitle="Manage your personal details and password." :breadcrumbs="['Profile' => null]" />

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="profile-cover"></div>
                <div class="card-body pt-0 text-center profile-head">
                    <x-avatar :name="$user->name" size="xl" />
                    <h2 class="h5 mt-3 mb-1">{{ $user->name }}</h2>
                    <div class="text-muted mb-3">{{ $user->email }}</div>
                    <span class="pill pill-{{ $user->role->value }} no-dot">{{ $user->role->label() }}</span>
                    <hr>
                    <div class="small text-muted">Member since {{ $user->created_at->format('F Y') }}</div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <form method="POST" action="{{ route('profile.update') }}" class="card mb-4" novalidate>
                @csrf
                @method('PUT')
                <div class="card-header"><h3 class="card-title">Personal information</h3></div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full name</label>
                            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required
                                   class="form-control @error('name', 'profile') is-invalid @enderror">
                            @error('name', 'profile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" required
                                   class="form-control @error('email', 'profile') is-invalid @enderror">
                            @error('email', 'profile') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent text-end py-3">
                    <button type="submit" class="btn btn-primary">Save profile</button>
                </div>
            </form>

            <form method="POST" action="{{ route('profile.password') }}" class="card" novalidate>
                @csrf
                @method('PUT')
                <div class="card-header">
                    <div>
                        <h3 class="card-title">Change password</h3>
                        <div class="card-subtitle">Other sessions and API tokens will be signed out.</div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="current_password" class="form-label">Current password</label>
                            <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                                   class="form-control @error('current_password', 'password') is-invalid @enderror">
                            @error('current_password', 'password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="new_password" class="form-label">New password</label>
                            <input type="password" id="new_password" name="password" autocomplete="new-password"
                                   class="form-control @error('password', 'password') is-invalid @enderror">
                            @error('password', 'password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="password_confirmation" class="form-label">Confirm new password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
                        </div>
                    </div>
                    <div class="form-text mt-2">Min 8 characters, with upper &amp; lower case letters and a number.</div>
                </div>
                <div class="card-footer bg-transparent text-end py-3">
                    <button type="submit" class="btn btn-primary">Update password</button>
                </div>
            </form>
        </div>
    </div>
@endsection
