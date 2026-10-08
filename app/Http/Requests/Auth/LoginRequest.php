<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Credential check shared by the web login form and the API token endpoint.
 * Brute force protection: 5 failed attempts per email + IP, then a lockout.
 */
class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['sometimes', 'string', 'max:100'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Validate the credentials and return the matching active user.
     *
     * @throws ValidationException
     */
    public function authenticateUser(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', mb_strtolower($this->string('email')->trim()))->first();

        // Always run a hash check so response timing does not reveal whether the email exists.
        $valid = Hash::check($this->string('password'), $user?->password ?? '$2y$12$'.str_repeat('a', 53));

        if (! $user || ! $valid) {
            RateLimiter::hit($this->throttleKey());
            $this->audit('auth.login_failed', 'Failed sign-in attempt', $user);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            $this->audit('auth.login_failed', 'Sign-in blocked: account deactivated', $user);

            throw ValidationException::withMessages(['email' => 'Your account has been deactivated.']);
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));
        $this->audit('auth.lockout', 'Sign-in locked after too many failed attempts');

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ])->status(429);
    }

    /**
     * Records the attempted email (never the password) for failed/locked sign-ins.
     */
    private function audit(string $event, string $description, ?User $user = null): void
    {
        app(ActivityLogger::class)->log(
            $event,
            $description,
            $user,
            ['email' => Str::limit(mb_strtolower((string) $this->input('email')), 120), 'channel' => $this->is('api/*') ? 'api' : 'web'],
            $user,
        );
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
