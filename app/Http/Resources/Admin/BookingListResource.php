<?php

namespace App\Http\Resources\Admin;

use App\Models\Booking;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the bookings desk (M11.1).
 *
 * @mixin Booking
 */
class BookingListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $driver = $this->driver;
        $payment = $this->latestPayment;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'car' => $this->car->name,
            'renter' => $driver?->fullName(),
            'phone' => $driver?->phone,
            'pickup_at' => $this->pickup_at->toIso8601String(),
            'return_at' => $this->return_at->toIso8601String(),
            'pickup_mode' => $this->pickup_mode->value,
            'return_mode' => $this->return_mode->value,
            'delivery_pending' => $this->deliveryPending(),
            'method' => $payment === null ? null : ($payment->method->name ?? $payment->payment_method_code->value),
            'total' => new Money((int) $this->total_minor),
            'balance' => new Money((int) $this->balance_minor),
        ];
    }
}
