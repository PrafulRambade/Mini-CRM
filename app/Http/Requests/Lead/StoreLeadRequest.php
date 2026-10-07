<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatus;
use App\Models\Lead;

class StoreLeadRequest extends LeadRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Lead::class);
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $defaults = [];

        if (! $this->filled('status')) {
            $defaults['status'] = LeadStatus::New->value;
        }

        // Leads created by a sales user are always owned by that user.
        if (! $this->filled('assigned_to') && ! $this->user()->isAdmin()) {
            $defaults['assigned_to'] = $this->user()->id;
        }

        $this->merge($defaults);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->baseRules();
        $rules['follow_up_date'][] = 'after_or_equal:today';

        return $rules;
    }
}
