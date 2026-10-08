<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the audit trail. Never stores passwords, tokens or free-text notes.
 * A logging failure must never break the user's action, so errors are only reported.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, string $description, ?Model $subject = null, array $properties = [], ?User $actor = null): void
    {
        try {
            $request = request();

            ActivityLog::create([
                'user_id' => ($actor ?? auth()->user())?->getKey(),
                'event' => $event,
                'description' => Str::limit($description, 250),
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'properties' => $properties ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250) : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to write activity log.', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }
}
