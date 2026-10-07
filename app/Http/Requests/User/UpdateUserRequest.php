<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\LockedForDemo;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends UserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->target());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = $this->baseRules($this->target());

        // An admin editing their own account cannot demote or deactivate themselves.
        if ($this->user()->is($this->target())) {
            $rules['role'][] = Rule::in([UserRole::Admin->value]);
            $rules['is_active'][] = 'accepted';
        }

        // Demo-safe mode: credentials, role and status of demo accounts are locked.
        $target = $this->target();
        if ($target->isProtectedDemoAccount()) {
            $rules['email'][] = new LockedForDemo($target->email);
            $rules['role'][] = new LockedForDemo($target->role->value);
            $rules['is_active'][] = new LockedForDemo($target->is_active ? '1' : '0');
            $rules['password'] = ['nullable', new LockedForDemo];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'You cannot remove your own admin role.',
            'is_active.accepted' => 'You cannot deactivate your own account.',
        ];
    }

    private function target(): User
    {
        return $this->route('user');
    }
}
