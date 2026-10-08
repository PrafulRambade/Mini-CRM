<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Builds listing links (sort, tabs, chips, pages) from the current filter state.
 *
 * Listings are loaded over AJAX (POST, CSRF-protected), so filters live in the
 * request body rather than the URL. Links rendered into the table still carry
 * the filter state so they work without JavaScript; the AJAX layer reads the
 * parameters from these links and posts them instead of navigating.
 */
final class Listing
{
    /** The only parameters a listing understands; everything else is dropped. */
    public const KEYS = ['search', 'status', 'source', 'assigned_to', 'role', 'group', 'sort', 'direction', 'per_page', 'page'];

    /**
     * Current filter state with overrides applied (null removes a key).
     * Non-scalar values (e.g. search[]=x) are discarded.
     *
     * @param  array<string, scalar|null>  $overrides
     * @return array<string, string>
     */
    public static function params(array $overrides = []): array
    {
        $params = collect(request()->only(self::KEYS))
            ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')
            ->map(fn ($value) => mb_substr((string) $value, 0, 100))
            ->all();

        foreach ($overrides as $key => $value) {
            if ($value === null || $value === '') {
                unset($params[$key]);
            } else {
                $params[$key] = (string) $value;
            }
        }

        return $params;
    }

    /**
     * Canonical listing URL (e.g. /leads) with the given state.
     *
     * @param  array<string, scalar|null>  $overrides
     */
    public static function url(array $overrides = []): string
    {
        $query = http_build_query(self::params($overrides));

        return self::base().($query !== '' ? '?'.$query : '');
    }

    /**
     * Point pagination links at the canonical listing URL with the current state.
     */
    public static function paginate(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->withPath(self::base())->appends(self::params(['page' => null]));
    }

    public static function setBase(string $url): void
    {
        request()->attributes->set('listing_base', $url);
    }

    private static function base(): string
    {
        return request()->attributes->get('listing_base', request()->url());
    }
}
