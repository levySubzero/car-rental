<?php

namespace App\Http\Resources\Admin;

use App\Models\BookingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingRequest
 */
class BookingRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->booking;
        $driver = $booking->driver;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'message' => $this->message,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'staff_note' => $this->staff_note,
            'handled_by' => $this->handler?->name,
            'handled_at' => $this->handled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'booking' => [
                'reference' => $booking->reference,
                'status_label' => $booking->status->label(),
                'car' => $booking->car->name,
                'pickup_at' => $booking->pickup_at->toIso8601String(),
                'renter' => $driver?->fullName(),
                'phone' => $driver?->phone,
                'email' => $driver?->email,
            ],
        ];
    }
}
