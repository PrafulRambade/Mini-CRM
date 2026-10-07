<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\IndexCustomerRequest;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(IndexCustomerRequest $request): View
    {
        $user = $request->user();

        $customers = Customer::query()
            ->visibleTo($user)
            ->with(['leads' => fn (HasMany $q) => $q
                ->when(! $user->isAdmin(), fn ($q) => $q->where('assigned_to', $user->id))
                ->select(['id', 'customer_id', 'name', 'assigned_to'])])
            ->search($request->search())
            ->sorted($request->sort(), $request->direction())
            ->paginate($request->perPage())
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $request->search(),
            'sort' => $request->sort() ?? 'created_at',
            'direction' => $request->direction(),
        ]);
    }
}
