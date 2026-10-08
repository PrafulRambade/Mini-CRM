<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\IndexCustomerRequest;
use App\Models\Customer;
use App\Support\Listing;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(IndexCustomerRequest $request): View
    {
        return view('customers.index', $this->listing($request));
    }

    /**
     * AJAX: the listing fragment for the given filters (POST body, CSRF-protected).
     */
    public function table(IndexCustomerRequest $request): View
    {
        return view('customers._listing', $this->listing($request));
    }

    /**
     * @return array<string, mixed>
     */
    private function listing(IndexCustomerRequest $request): array
    {
        Listing::setBase(route('customers.index'));
        $user = $request->user();

        $customers = Customer::query()
            ->visibleTo($user)
            ->with(['leads' => fn (HasMany $q) => $q
                ->when(! $user->isAdmin(), fn ($q) => $q->where('assigned_to', $user->id))
                ->select(['id', 'customer_id', 'name', 'assigned_to'])])
            ->search($request->search())
            ->sorted($request->sort(), $request->direction())
            ->paginate($request->perPage());

        return [
            'customers' => Listing::paginate($customers),
            'search' => $request->search(),
            'sort' => $request->sort() ?? 'created_at',
            'direction' => $request->direction(),
        ];
    }
}
