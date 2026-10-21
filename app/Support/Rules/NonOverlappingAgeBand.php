<?php

namespace App\Support\Rules;

use App\Models\DepositBand;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An active deposit band's ages may not overlap another active band's (M6.7), or the
 * deposit for a driver would be ambiguous. Put it on `min_age`; it reads `max_age` too.
 */
class NonOverlappingAgeBand implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly ?DepositBand $ignore = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $max = $this->data['max_age'] ?? null;
        $active = filter_var($this->data['is_active'] ?? true, FILTER_VALIDATE_BOOL);

        if (! $active || ! is_numeric($value) || ! is_numeric($max)) {
            return;
        }

        $clash = DepositBand::query()->active()
            ->when($this->ignore, fn ($query) => $query->whereKeyNot($this->ignore->id))
            ->where('min_age', '<=', (int) $max)
            ->where('max_age', '>=', (int) $value)
            ->first();

        if ($clash !== null) {
            $fail("These ages overlap the {$clash->min_age}–{$clash->max_age} band.");
        }
    }
}
