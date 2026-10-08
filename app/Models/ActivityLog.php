<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail. There is deliberately no update/delete path in the
 * application; old rows are removed only by scheduled pruning.
 */
class ActivityLog extends Model
{
    use MassPrunable, Searchable;

    public const RETENTION_DAYS = 180;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** @var list<string> */
    protected array $searchable = ['description', 'event', 'ip_address'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Bootstrap icon + tone for the event's category, used by the admin view.
     *
     * @return array{0: string, 1: string}
     */
    public function presentation(): array
    {
        return match (true) {
            str_ends_with($this->event, 'failed'), $this->event === 'auth.lockout' => ['bi-shield-exclamation', 'danger'],
            str_starts_with($this->event, 'auth.') => ['bi-box-arrow-in-right', 'neutral'],
            $this->event === 'lead.converted' => ['bi-trophy', 'success'],
            $this->event === 'lead.deleted', $this->event === 'user.deactivated' => ['bi-trash', 'danger'],
            str_starts_with($this->event, 'lead.') => ['bi-funnel', 'primary'],
            default => ['bi-person-gear', 'warning'],
        };
    }
}
