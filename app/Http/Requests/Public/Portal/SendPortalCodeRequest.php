<?php

namespace App\Http\Requests\Public\Portal;

use App\Services\Booking\BookingPortalService;
use App\Support\Rules\Turnstile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asks for a portal code (M10B). Open to anyone: Turnstile and the `portal` limiter keep
 * bots out.
 */
class SendPortalCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (app(BookingPortalService::class)->identify((string) $value) === null) {
                    $fail('Enter the email address or phone number you booked with.');
                }
            }],
            'cf-turnstile-response' => [new Turnstile($this->ip())],
        ];
    }

    /**
     * @return array{type: 'email'|'phone', value: string}
     */
    public function identity(): array
    {
        return app(BookingPortalService::class)->identify((string) $this->validated('identifier'))
            ?? throw new \LogicException('Validated identifier did not parse.');
    }
}
