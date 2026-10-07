<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for creating and updating leads (web + API).
 */
abstract class LeadRequest extends FormRequest
{
    public const PHONE_REGEX = '/^\+?[0-9][0-9\s\-().]{5,18}[0-9]$/';

    protected function prepareForValidation(): void
    {
        if ($this->filled('email')) {
            $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX],
            'company' => ['nullable', 'string', 'max:255'],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'assigned_to' => $this->assigneeRules(),
            'follow_up_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Admins may assign to any active user (or leave unassigned); sales users
     * may only keep leads assigned to themselves and can never unassign them.
     *
     * @return list<mixed>
     */
    protected function assigneeRules(): array
    {
        /** @var User $user */
        $user = $this->user();

        if ($user->can('assign', Lead::class)) {
            return ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)];
        }

        return ['sometimes', 'required', 'integer', Rule::in([$user->id])];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number must be 7-20 characters and may contain digits, spaces, +, -, ( and ).',
            'assigned_to.in' => 'You may only assign leads to yourself.',
            'assigned_to.exists' => 'The selected user does not exist or is inactive.',
            'follow_up_date.after_or_equal' => 'The follow-up date cannot be in the past.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'assigned_to' => 'assignee',
            'follow_up_date' => 'follow-up date',
        ];
    }
}
