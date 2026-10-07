<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Http\Requests\ListingRequest;
use App\Models\Lead;
use Illuminate\Validation\Rule;

class IndexLeadRequest extends ListingRequest
{
    protected function sortable(): array
    {
        return Lead::SORTABLE;
    }

    protected function filterRules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'source' => ['nullable', Rule::enum(LeadSource::class)],
            'assigned_to' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Sanitised filters ready for `Lead::filter()`.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $assignedTo = filter_var($this->query('assigned_to'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'search' => $this->search(),
            'status' => LeadStatus::tryFrom((string) $this->query('status'))?->value,
            'source' => LeadSource::tryFrom((string) $this->query('source'))?->value,
            // Only admins can filter by assignee; sales users are already scoped to themselves.
            'assigned_to' => $this->user()->isAdmin() && $assignedTo !== false ? $assignedTo : null,
        ];
    }
}
