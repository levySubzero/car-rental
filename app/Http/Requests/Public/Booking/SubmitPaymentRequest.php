<?php

namespace App\Http\Requests\Public\Booking;

use App\Http\Middleware\ResolveBookingToken;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Services\Booking\BookingTokenResolver;
use App\Support\Rules\PendingOrOwnedMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Steps 4 and 5 (M9.2, M9.3): the method the renter chose, and for every method but cash
 * the transaction message and an optional proof. Each method's flags decide what is required.
 */
class SubmitPaymentRequest extends FormRequest
{
    private ?PaymentMethod $chosen = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $method = $this->chosenMethod();

        return [
            'method' => ['required', 'string', Rule::exists('payment_methods', 'code')->where('is_active', true)],
            'transaction_message' => [$method?->requires_transaction_message ? 'required' : 'nullable', 'string', 'max:1000'],
            'proof' => [
                $method?->requires_proof ? 'required' : 'nullable',
                'uuid',
                new PendingOrOwnedMedia(BookingTokenResolver::PAYMENT_PROOF, $this->booking(), $this->booking()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.required' => 'Choose how you will pay.',
            'method.exists' => 'This payment method is not available at the moment. Please choose another.',
            'transaction_message.required' => 'Paste the confirmation message you received for this payment.',
            'proof.required' => 'Attach a screenshot or receipt of the payment.',
        ];
    }

    public function booking(): Booking
    {
        return ResolveBookingToken::booking($this);
    }

    public function paymentMethod(): PaymentMethod
    {
        return $this->chosenMethod() ?? abort(422);
    }

    public function transactionMessage(): ?string
    {
        $message = $this->validated('transaction_message');

        return is_string($message) && trim($message) !== '' ? trim($message) : null;
    }

    public function proof(): ?string
    {
        $uuid = $this->validated('proof');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    private function chosenMethod(): ?PaymentMethod
    {
        $code = $this->input('method');

        return $this->chosen ??= is_string($code) ? PaymentMethod::query()->active()->where('code', $code)->first() : null;
    }
}
