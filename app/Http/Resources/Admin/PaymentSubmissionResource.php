<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\Media\MediaResource;
use App\Models\PaymentSubmission;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment in the staff queue, with what is needed to check it against the statement.
 *
 * @mixin PaymentSubmission
 */
class PaymentSubmissionResource extends JsonResource
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
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'method' => $this->payment_method_code->value,
            'method_name' => $this->method->name ?? $this->payment_method_code->value,
            'transaction_message' => $this->transaction_message,
            'proof' => $this->proof ? MediaResource::make($this->proof) : null,
            'amount' => $this->amount_minor === null ? null : new Money($this->amount_minor),
            'reference' => $this->reference,
            'note' => $this->note,
            'rejection_reason' => $this->rejection_reason,
            // Online payments (M11B.8) are verified by the system, not a staff member.
            'online' => $this->gatewayTransaction === null ? null : [
                'gateway' => $this->gatewayTransaction->gateway->value,
                'label' => $this->gatewayTransaction->gateway->label(),
            ],
            'verified_by' => $this->verifier->name ?? ($this->gatewayTransaction !== null ? 'Verified automatically' : null),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'rejected_by' => $this->rejecter?->name,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'booking' => [
                'reference' => $booking->reference,
                'status' => $booking->status->value,
                'status_label' => $booking->status->label(),
                'car' => $booking->car->name,
                'renter' => $driver ? trim("{$driver->first_name} {$driver->surname}") : null,
                'phone' => $driver?->phone,
                'pickup_at' => $booking->pickup_at->toIso8601String(),
                'expires_at' => $booking->expires_at?->toIso8601String(),
                'total' => new Money((int) $booking->total_minor),
                'reservation' => new Money((int) $booking->reservation_minor),
            ],
        ];
    }
}
