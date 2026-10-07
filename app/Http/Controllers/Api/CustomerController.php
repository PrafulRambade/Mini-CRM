<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\IndexCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(IndexCustomerRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $customers = Customer::query()
            ->visibleTo($user)
            ->with(['leads' => $this->visibleLeads($user)])
            ->search($request->search())
            ->sorted($request->sort(), $request->direction())
            ->paginate($request->perPage())
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    public function show(Request $request, Customer $customer): CustomerResource
    {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer->load(['leads' => $this->visibleLeads($request->user())]));
    }

    /**
     * A customer may originate from several leads (same email); sales users
     * should only see the ones assigned to them.
     */
    private function visibleLeads(User $user): \Closure
    {
        return fn (HasMany $q) => $q->when(! $user->isAdmin(), fn ($q) => $q->where('assigned_to', $user->id));
    }
}
