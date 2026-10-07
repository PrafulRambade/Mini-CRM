<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, Searchable, SoftDeletes;

    public const SORTABLE = ['name', 'email', 'company', 'created_at'];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
    ];

    /** @var list<string> */
    protected array $searchable = ['name', 'email', 'phone', 'company'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Admins see every customer; sales users see customers converted from
     * leads assigned to them.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin()
            ? $query
            : $query->whereHas('leads', fn (Builder $q) => $q->where('assigned_to', $user->id));
    }

    public function scopeSorted(Builder $query, ?string $sort, ?string $direction): Builder
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'created_at';
        $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }
}
