<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListingRequest;
use App\Models\ActivityLog;
use App\Support\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    /** Filter groups shown in the UI, mapped to event names or prefixes. */
    public const GROUPS = [
        'security' => ['label' => 'Security alerts', 'events' => ['auth.login_failed', 'auth.lockout']],
        'auth' => ['label' => 'Sign-ins', 'prefix' => 'auth.'],
        'lead' => ['label' => 'Leads', 'prefix' => 'lead.'],
        'user' => ['label' => 'Users', 'prefix' => 'user.'],
    ];

    public function index(Request $request): View
    {
        return view('activity.index', $this->listing($request));
    }

    /**
     * AJAX: the listing fragment for the given filters (POST body, CSRF-protected).
     */
    public function table(Request $request): View
    {
        return view('activity._listing', $this->listing($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function listing(Request $request): array
    {
        Gate::authorize('viewActivityLog');
        Listing::setBase(route('activity.index'));

        $search = is_string($request->input('search')) ? mb_substr(trim($request->input('search')), 0, 100) : null;
        $groupKey = is_string($request->input('group')) ? $request->input('group') : null;
        $group = array_key_exists((string) $groupKey, self::GROUPS) ? $groupKey : null;

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
            ->paginate(ListingRequest::DEFAULT_PER_PAGE * 2);

        return [
            'logs' => Listing::paginate($logs),
            'search' => $search,
            'group' => $group,
            'groups' => self::GROUPS,
            'retentionDays' => ActivityLog::RETENTION_DAYS,
        ];
    }
}
