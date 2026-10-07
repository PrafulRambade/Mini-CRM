<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Concerns\Searchable;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, Searchable, SoftDeletes;

    /** Columns users may sort the listing by. */
    public const SORTABLE = ['name', 'email', 'company', 'status', 'source', 'follow_up_date', 'created_at'];

    /**
     * `customer_id`, `converted_at` and `created_by` are system-managed and
     * deliberately excluded from mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'source',
        'status',
        'assigned_to',
        'follow_up_date',
        'notes',
    ];

    /** @var list<string> */
    protected array $searchable = ['name', 'email', 'phone', 'company'];

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'follow_up_date' => 'date',
            'converted_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isConverted(): bool
    {
        return $this->customer_id !== null;
    }

    /**
     * Restrict the query to leads the given user is allowed to see.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('assigned_to', $user->id);
    }

    /**
     * Apply the optional listing filters (status, source, assignee).
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))
            ->when($filters['source'] ?? null, fn (Builder $q, $source) => $q->where('source', $source))
            ->when($filters['assigned_to'] ?? null, fn (Builder $q, $userId) => $q->where('assigned_to', $userId));
    }

    public function scopeSorted(Builder $query, ?string $sort, ?string $direction): Builder
    {
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'created_at';
        $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }
}
