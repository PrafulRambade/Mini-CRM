<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects any change to a field of a protected demo account.
 * With no current value given, the field must be left empty (e.g. password).
 */
class LockedForDemo implements ValidationRule
{
    public const MESSAGE = 'This is a protected demo account, so its :attribute cannot be changed.';

    public function __construct(private readonly mixed $current = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $changed = $this->current === null
            ? filled($value)
            : mb_strtolower((string) $value) !== mb_strtolower((string) $this->current);

        if ($changed) {
            $fail(self::MESSAGE);
        }
    }
}
