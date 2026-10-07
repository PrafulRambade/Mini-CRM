<?php

namespace App\Http\Requests\Lead;

use App\Models\Lead;
use Closure;

class UpdateLeadRequest extends LeadRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->lead());
    }

    /**
     * PUT replaces the whole resource; PATCH accepts partial payloads.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->baseRules();
        $rules['follow_up_date'][] = $this->followUpDateRule();

        if ($this->isMethod('PATCH')) {
            $rules = array_map(fn (array $fieldRules) => ['sometimes', ...$fieldRules], $rules);
        }

        return $rules;
    }

    /**
     * A past follow-up date is only rejected when it is being changed, so an
     * older lead can still be edited without touching its existing date.
     */
    private function followUpDateRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $current = $this->lead()->follow_up_date?->toDateString();

            if ($value !== $current && is_string($value) && $value < now()->toDateString()) {
                $fail('The follow-up date cannot be in the past.');
            }
        };
    }

    private function lead(): Lead
    {
        return $this->route('lead');
    }
}
