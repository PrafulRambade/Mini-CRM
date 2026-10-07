<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Models\User;
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
