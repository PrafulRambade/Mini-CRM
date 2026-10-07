<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\Searchable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, Searchable;

    /** @var list<string> */
    protected array $searchable = ['name', 'email'];

    /**
     * Role and active flag are intentionally NOT mass assignable so they can
     * never be escalated through request payloads.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'role' => 'sales',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSales(): bool
    {
        return $this->role === UserRole::Sales;
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    /**
     * True for the seeded demo accounts while demo-safe mode is on; their
     * credentials, role and active flag are then locked.
     */
    public function isProtectedDemoAccount(): bool
    {
        return config('app.demo_mode')
            && array_key_exists(mb_strtolower((string) $this->getOriginal('email')), config('app.demo_accounts', []));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Users that leads may be assigned to.
     */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query->active()->orderBy('name');
    }
}
