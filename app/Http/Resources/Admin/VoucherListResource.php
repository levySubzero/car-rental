<?php

namespace App\Http\Resources\Admin;

use App\Enums\BookingSource;
use App\Models\Booking;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row on the Vouchers screen.
 *
 * @mixin Booking
 */
class VoucherListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $driver = $this->driver;

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
            'sent_at' => $this->voucher_sent_at?->toIso8601String(),
            // Staff hand these over themselves: there is no address to email.
            'by_hand' => $this->source === BookingSource::Admin && blank($driver?->email),
            'total' => new Money((int) $this->total_minor),
        ];
    }
}
