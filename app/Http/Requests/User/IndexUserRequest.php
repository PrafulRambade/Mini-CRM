<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use App\Http\Requests\ListingRequest;
use App\Models\User;

class IndexUserRequest extends ListingRequest
{
    public const SORTABLE = ['name', 'email', 'role', 'created_at'];

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    protected function sortable(): array
    {
        return self::SORTABLE;
    }

    /**
     * @return array{search: ?string, role: ?string, status: ?string}
     */
    public function filters(): array
    {
        $status = $this->query('status');

        return [
            'search' => $this->search(),
            'role' => UserRole::tryFrom((string) $this->query('role'))?->value,
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : null,
        ];
    }
}
