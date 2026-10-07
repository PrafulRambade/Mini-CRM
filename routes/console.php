<?php

use Illuminate\Support\Facades\Schedule;

// Remove API tokens that expired more than 24 hours ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
