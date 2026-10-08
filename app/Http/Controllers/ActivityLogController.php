<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListingRequest;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /** Filter groups shown in the UI, mapped to event-name prefixes. */
    public const GROUPS = [
        'security' => ['label' => 'Security alerts', 'events' => ['auth.login_failed', 'auth.lockout']],
        'auth' => ['label' => 'Sign-ins', 'prefix' => 'auth.'],
        'lead' => ['label' => 'Leads', 'prefix' => 'lead.'],
        'user' => ['label' => 'Users', 'prefix' => 'user.'],
    ];

    public function index(Request $request): View
    {
        Gate::authorize('viewActivityLog');

        $search = is_string($request->query('search')) ? mb_substr(trim($request->query('search')), 0, 100) : null;
        $group = array_key_exists((string) $request->query('group'), self::GROUPS) ? $request->query('group') : null;

        $logs = ActivityLog::query()
            ->with('user:id,name,email')
            ->search($search)
            ->when($group, function ($q) use ($group) {
                $def = self::GROUPS[$group];

                return isset($def['events'])
                    ? $q->whereIn('event', $def['events'])
                    : $q->where('event', 'like', $def['prefix'].'%');
            })
            ->latest('id')
            ->paginate(ListingRequest::DEFAULT_PER_PAGE * 2)
            ->withQueryString();

        return view('activity.index', [
            'logs' => $logs,
            'search' => $search,
            'group' => $group,
            'groups' => self::GROUPS,
            'retentionDays' => ActivityLog::RETENTION_DAYS,
        ]);
    }
}
