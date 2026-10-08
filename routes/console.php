<?php

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Schedule;

// Remove API tokens that expired more than 24 hours ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Audit trail retention: delete activity log entries older than ActivityLog::RETENTION_DAYS.
Schedule::command('model:prune', ['--model' => [ActivityLog::class]])->daily();
