<?php

namespace App\Support\Rules;

use App\Services\Media\MediaCollectionRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A list of media uuids no longer than the collection allows (after admin overrides).
 *
 *     'gallery' => ['array', new MaxMediaForCollection('car.gallery')],
 */
class MaxMediaForCollection implements ValidationRule
{
    public function __construct(private readonly string $collection) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $max = app(MediaCollectionRegistry::class)->get($this->collection)->maxFiles;

        if (is_array($value) && count(array_unique($value)) > $max) {
            $fail("You can add at most {$max} file(s) here.");
        }
    }
}
