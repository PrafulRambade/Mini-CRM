<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Support\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(IndexUserRequest $request): View
    {
        return view('users.index', $this->listing($request));
    }

    /**
     * AJAX: the listing fragment for the given filters (POST body, CSRF-protected).
     */
    public function table(IndexUserRequest $request): View
    {
        return view('users._listing', $this->listing($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function listing(IndexUserRequest $request): array
    {
        Listing::setBase(route('users.index'));
        $filters = $request->filters();

        $users = User::query()
            ->withCount([
                'assignedLeads',
                'assignedLeads as won_leads_count' => fn ($q) => $q->where('status', LeadStatus::Won->value),
            ])
            ->search($filters['search'])
            ->when($filters['role'], fn ($q, $role) => $q->where('role', $role))
            ->when($filters['status'], fn ($q, $status) => $q->where('is_active', $status === 'active'))
            ->orderBy($request->sort() ?? 'created_at', $request->direction())
            ->orderBy('id')
            ->paginate($request->perPage());

        return [
            'users' => Listing::paginate($users),
            'filters' => $filters,
            'roles' => UserRole::cases(),
            'sort' => $request->sort() ?? 'created_at',
            'direction' => $request->direction(),
        ];
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', ['user' => new User, 'roles' => UserRole::cases()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($request->validated());

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" created.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $request->validated(), $request->session()->getId());

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" updated.");
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        Gate::authorize('toggleStatus', $user);

        $this->users->toggleStatus($user);

        $state = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "User \"{$user->name}\" {$state}.");
    }
}
