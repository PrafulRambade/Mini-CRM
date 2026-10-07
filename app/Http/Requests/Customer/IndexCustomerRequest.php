<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\ListingRequest;
use App\Models\Customer;

class IndexCustomerRequest extends ListingRequest
{
    protected function sortable(): array
    {
        return Customer::SORTABLE;
    }
}
