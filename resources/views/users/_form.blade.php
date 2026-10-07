@php
    /** @var \App\Models\User $user */
    $isSelf = $user->exists && auth()->user()->is($user);
    $currentRole = old('role', $user->role?->value ?? \App\Enums\UserRole::Sales->value);
    $active = (bool) old('is_active', $user->exists ? $user->is_active : true);
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="form-section-title">Profile</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-person"></i>
                            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required
                                   class="form-control @error('name') is-invalid @enderror">
                        </div>
                        @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-envelope"></i>
                            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" required
                                   autocomplete="off" class="form-control @error('email') is-invalid @enderror">
                        </div>
                        @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="form-section-title">{{ $user->exists ? 'Reset password' : 'Password' }}</h2>
                @if ($user->exists)
                    <p class="small text-muted mt-n2">Leave blank to keep the current password. Setting a new one signs the user out of all other sessions and revokes their API tokens.</p>
                @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password @unless($user->exists)<span class="text-danger">*</span>@endunless</label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="password" name="password" autocomplete="new-password"
                                   class="form-control @error('password') is-invalid @enderror">
                        </div>
                        @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <div class="form-text">Min 8 characters, with upper &amp; lower case letters and a number.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label">Confirm password</label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h2 class="form-section-title">Access</h2>

                <span class="form-label d-block">Role <span class="text-danger">*</span></span>
                <div class="d-grid gap-2 mb-3" role="radiogroup" aria-label="Role">
                    @foreach ($roles as $role)
                        <input type="radio" class="btn-check" name="role" id="role-{{ $role->value }}" value="{{ $role->value }}"
                               autocomplete="off" @checked($currentRole === $role->value) @disabled($isSelf && $role !== \App\Enums\UserRole::Admin)>
                        <label class="btn btn-light-soft text-start justify-content-start p-3" for="role-{{ $role->value }}">
                            <span class="d-flex gap-3 align-items-start">
                                <i class="bi {{ $role === \App\Enums\UserRole::Admin ? 'bi-shield-check' : 'bi-briefcase' }} fs-5"></i>
                                <span>
                                    <span class="d-block fw-semibold">{{ $role->label() }}</span>
                                    <span class="d-block small fw-normal text-muted">
                                        {{ $role === \App\Enums\UserRole::Admin ? 'Full access: all leads, customers and user management.' : 'Own leads only; cannot delete or reassign.' }}
                                    </span>
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('role') <div class="invalid-feedback d-block mb-3">{{ $message }}</div> @enderror

                <div class="form-check form-switch d-flex align-items-center gap-2 ps-0">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input ms-0" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                           @checked($active) @disabled($isSelf)>
                    @if ($isSelf)<input type="hidden" name="is_active" value="1">@endif
                    <label class="form-check-label" for="is_active">Account active</label>
                </div>
                @error('is_active') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                @if ($isSelf)
                    <div class="form-text mt-2"><i class="bi bi-info-circle"></i> You can't change your own role or deactivate yourself.</div>
                @endif
            </div>
        </div>

        <style>
            .btn-check:checked + .btn-light-soft { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }
            .btn-check:checked + .btn-light-soft .text-muted { color: var(--text-2) !important; }
        </style>
    </div>
</div>
