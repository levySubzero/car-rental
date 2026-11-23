<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One extension of a hire (D50), as a row under the booking it extends: on the renter's
 * page, in the portal and on the desk.
 *
 * @mixin Booking
 */
class ExtensionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'from' => $this->pickup_at->toIso8601String(),
            'to' => $this->return_at->toIso8601String(),
            'days' => (int) ($this->quote['days'] ?? 0),
            'total' => new Money((int) $this->total_minor),
            'paid' => $this->reservation_paid_minor === null ? null : new Money($this->reservation_paid_minor),
            'awaiting_payment' => $this->status->acceptsPayment(),
            'live' => in_array($this->status, Booking::LIVE_EXTENSION, true),
        ];
    }
}
