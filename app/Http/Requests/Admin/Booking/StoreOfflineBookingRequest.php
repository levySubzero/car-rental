<?php

namespace App\Http\Requests\Admin\Booking;

use App\Enums\PaymentMethodCode;
use App\Http\Requests\Public\Booking\Concerns\ValidatesTrip;
use App\Models\Booking;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A booking for a client who did not book online: the trip, how to reach them, and what staff
 * agreed and took. Staff may leave the price empty to use the car's normal rate.
 */
class StoreOfflineBookingRequest extends FormRequest
{
    use ValidatesTrip;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $amount = ['nullable', 'numeric', 'min:0', 'max:100000000', 'decimal:0,2'];

        return [
            ...$this->tripRules(),
            // A walk-in can take the car today.
            'pickup_at' => ['required', 'date', 'after_or_equal:today'],
            'car_id' => ['required', 'integer', Rule::exists('cars', 'id')->whereNull('deleted_at')],
            'first_name' => ['required', 'string', 'max:80'],
            'surname' => ['required', 'string', 'max:80'],
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:30', self::PHONE],
            'total' => $amount,
            'deposit' => $amount,
            'paid' => $amount,
            'payment_method' => ['required_with:paid', 'nullable', Rule::enum(PaymentMethodCode::class)],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->tripMessages(),
            'pickup_at.after_or_equal' => 'The pickup cannot be before today.',
            'email.required_without' => 'Give an email or a phone number for the client.',
            'phone.required_without' => 'Give an email or a phone number for the client.',
            'payment_method.required_with' => 'Say how the client paid.',
        ];
    }

    public function money(string $key): ?Money
    {
        return filled($this->validated($key)) ? Money::fromMajor((string) $this->validated($key)) : null;
    }
}
