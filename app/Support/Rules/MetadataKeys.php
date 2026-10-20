<?php

namespace App\Support\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keys of a `metadata` object (D20): snake_case, starting with a letter, at most 40 characters.
 * Pair it with rules for the values:
 *
 *     'metadata' => ['nullable', 'array', 'max:30', new MetadataKeys],
 *     'metadata.*' => ['nullable', 'string', 'max:500'],
 */
class MetadataKeys implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) !== 1) {
                $fail('Extra info names use lowercase letters, numbers and underscores, and start with a letter (e.g. top_speed_kmh).');

                return;
            }
        }
    }
}
