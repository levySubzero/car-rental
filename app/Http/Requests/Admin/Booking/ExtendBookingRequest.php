<?php

namespace App\Http\Requests\Admin\Booking;

use App\Enums\PaymentMethodCode;
use App\Services\Booking\BookingExtensionService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Staff extending a confirmed or running hire (D50): a new return, an optional price of their
 * own, and either a link for the renter to pay or a payment staff took. The same rules serve
 * the live preview, which reads only `return_at`.
 */
class ExtendBookingRequest extends BookingActionRequest
{
    protected function ability(): string
    {
        return 'extend';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $preview = $this->routeIs('admin.bookings.extend.preview');

        return [
            'return_at' => ['required', 'date', 'after:'.$this->booking()->chainEnd()->toIso8601String(), 'before:+1 year'],
            'total' => $this->amountRules(required: false),
            'payment' => [$preview ? 'nullable' : 'required', Rule::in([BookingExtensionService::PAY_LINK, BookingExtensionService::PAY_RECORDED])],
            'paid' => $this->amountRules(required: false),
            'method' => ['nullable', 'required_with:paid', Rule::enum(PaymentMethodCode::class)],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'return_at.after' => 'The new return must be after the current one.',
        ];
    }

    public function returnAt(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('return_at'))->utc();
    }

    public function total(): ?Money
    {
        return filled($this->validated('total')) ? $this->money('total') : null;
    }

    /**
     * @return BookingExtensionService::PAY_*
     */
    public function payment(): string
    {
        return $this->validated('payment') === BookingExtensionService::PAY_RECORDED
            ? BookingExtensionService::PAY_RECORDED
            : BookingExtensionService::PAY_LINK;
    }

    public function paid(): ?Money
    {
        return filled($this->validated('paid')) ? $this->money('paid') : null;
    }

    public function paymentMethod(): ?PaymentMethodCode
    {
        return filled($this->validated('method')) ? PaymentMethodCode::from((string) $this->validated('method')) : null;
    }
}
