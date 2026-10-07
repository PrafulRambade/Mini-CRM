<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base request for paginated, searchable, sortable listings.
 *
 * API requests get strict validation (422 on bad input). Browser requests are
 * never rejected; invalid values are simply ignored by `filters()`, which avoids
 * redirect loops on a GET listing page with a tampered query string.
 */
abstract class ListingRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /**
     * @return list<string>
     */
    abstract protected function sortable(): array;

    /**
     * Extra filter rules for the concrete listing.
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->expectsJson()) {
            return [];
        }

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in($this->sortable())],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:1,'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
            ...$this->filterRules(),
        ];
    }

    public function search(): ?string
    {
        $search = $this->query('search');

        return is_string($search) ? mb_substr(trim($search), 0, 100) : null;
    }

    public function sort(): ?string
    {
        $sort = $this->query('sort');

        return in_array($sort, $this->sortable(), true) ? $sort : null;
    }

    public function direction(): string
    {
        return $this->query('direction') === 'asc' ? 'asc' : 'desc';
    }

    public function perPage(): int
    {
        $perPage = filter_var($this->query('per_page'), FILTER_VALIDATE_INT);

        return $perPage === false
            ? self::DEFAULT_PER_PAGE
            : max(1, min(self::MAX_PER_PAGE, $perPage));
    }
}
