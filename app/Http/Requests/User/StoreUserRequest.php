<?php

namespace App\Http\Requests\User;

use App\Models\User;

class StoreUserRequest extends UserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->baseRules();
    }
}
