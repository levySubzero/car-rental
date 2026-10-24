<?php

namespace App\Http\Requests\Admin\Booking;

use App\Models\Booking;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A desk action on one booking (M11), authorized by the BookingPolicy ability it names.
 * Amounts arrive in KES major units.
 */
abstract class BookingActionRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking && ($this->user()?->can($this->ability(), $booking) ?? false);
    }

    public function booking(): Booking
    {
        /** @var Booking */
        return $this->route('booking');
    }

    public function money(string $key): Money
    {
        return Money::fromMajor((string) $this->validated($key));
    }

    public function text(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    protected function amountRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'];
    }
}
