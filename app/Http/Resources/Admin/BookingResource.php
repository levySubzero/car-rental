<?php

namespace App\Http\Resources\Admin;

use App\Enums\BookingDocument;
use App\Enums\Permission;
use App\Http\Resources\Media\MediaResource;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\HandoverRecord;
use App\Models\PaymentSubmission;
use App\Services\Notifications\VoucherService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One booking on the desk (M11.2). Identity numbers are decrypted, and KYC files listed, only
 * for staff with bookings.view-documents; others get masked hints. The deposit is shown to
 * everyone who takes it at handover, but its band (which reveals the car's value) only with
 * cars.view-value.
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $documents = $user?->can('viewMedia', $this->resource) ?? false;
        $quote = $this->quote;

        if ($quote !== null && ! ($user?->can(Permission::CarsViewValue) ?? false)) {
            $quote['deposit_band'] = null;
        }

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'car' => ['id' => $this->car->id, 'name' => $this->car->name, 'slug' => $this->car->slug],
            'pickup_at' => $this->pickup_at->toIso8601String(),
            'return_at' => $this->return_at->toIso8601String(),
            'pickup_mode' => $this->pickup_mode->value,
            'pickup_mode_label' => $this->pickup_mode->label(),
            'return_mode' => $this->return_mode->value,
            'return_mode_label' => $this->return_mode->label(true),
            'flight_number' => $this->flight_number,
            'delivery' => $this->needsDeliveryZone() ? [
                'zone' => $this->deliveryZone === null ? null : [
                    'id' => $this->deliveryZone->id,
                    'name' => $this->deliveryZone->name,
                    'description' => $this->deliveryZone->description,
                ],
                'pickup' => $this->leg('delivery'),
                'return' => $this->leg('return'),
                'fee' => $this->deliveryFeeMinor() === null ? null : new Money((int) $this->deliveryFeeMinor()),
                'pending' => $this->deliveryPending(),
                'checked_at' => $this->delivery_checked_at?->toIso8601String(),
            ] : null,
            'total' => new Money((int) $this->total_minor),
            'reservation' => new Money((int) $this->reservation_minor),
            'reservation_paid' => $this->reservation_paid_minor === null ? null : new Money($this->reservation_paid_minor),
            'balance' => new Money((int) $this->balance_minor),
            // Taken short at handover: still owing (null before handover or when settled).
            'balance_outstanding' => ($outstanding = $this->balanceOutstandingMinor()) ? new Money($outstanding) : null,
            'security_deposit' => $this->security_deposit_minor === null ? null : new Money($this->security_deposit_minor),
            'quote' => $quote,
            'cancellation_tiers' => $this->cancellation_tiers ?? [],
            'driver' => $this->driverData($documents),
            'documents' => $documents ? $this->documentList() : null,
            'voucher' => ($voucher = app(VoucherService::class)->current($this->resource)) ? MediaResource::make($voucher) : null,
            'payments' => $this->payments->map(fn (PaymentSubmission $payment) => PaymentSubmissionResource::make($payment->setRelation('booking', $this->resource))->resolve($request))->all(),
            'handover' => $this->record($this->handover),
            'return' => $this->record($this->returnRecord),
            'cancellation' => $this->cancellation_reason === null ? null : [
                'reason' => $this->cancellation_reason,
                'tier' => $this->refund_tier,
                'suggested' => new Money((int) $this->refund_suggested_minor),
                'refund' => new Money((int) $this->refund_minor),
                'override_reason' => $this->refund_override_reason,
                'reference' => $this->refund_reference,
                'refunded_at' => $this->refunded_at?->toIso8601String(),
            ],
            'requests' => $this->requests->map(fn (BookingRequest $item) => [
                'id' => $item->id,
                'type_label' => $item->type->label(),
                'message' => $item->message,
                'status' => $item->status->value,
                'staff_note' => $item->staff_note,
                'created_at' => $item->created_at?->toIso8601String(),
            ])->all(),
            'legal_hold' => $this->legal_hold,
            'kyc_purged_at' => $this->kyc_purged_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'accepted_terms_at' => $this->accepted_terms_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function leg(string $prefix): array
    {
        return [
            'address' => $this->{"{$prefix}_address"},
            'landmark' => $this->{"{$prefix}_landmark"},
            'lat' => $this->{"{$prefix}_lat"},
            'lng' => $this->{"{$prefix}_lng"},
            'contact_phone' => $this->{"{$prefix}_contact_phone"},
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function driverData(bool $full): ?array
    {
        $driver = $this->driver;

        if ($driver === null) {
            return null;
        }

        $show = fn (?string $value) => $value === null || $value === '' ? null : ($full ? $value : self::mask($value));

        return [
            'name' => $driver->fullName(),
            'dob' => $driver->dob->toDateString(),
            'email' => $driver->email,
            'phone' => $driver->phone,
            'nationality' => $driver->nationality,
            'is_kenyan_resident' => $driver->is_kenyan_resident,
            'id_type' => $driver->id_type->label(),
            'id_number' => $show($driver->id_number),
            'kra_pin' => $show($driver->kra_pin),
            'licence_number' => $show($driver->licence_number),
            'licence_expiry' => $driver->licence_expiry->toDateString(),
            'licence_years' => $driver->licence_years_declared,
            'masked' => ! $full,
        ];
    }

    /**
     * @return list<array{key: string, label: string, file: MediaResource|null}>
     */
    private function documentList(): array
    {
        $files = $this->media()->whereIn('collection', array_map(fn (BookingDocument $d) => $d->collection(), BookingDocument::cases()))->get()->keyBy('collection');
        $list = [];

        foreach (BookingDocument::cases() as $document) {
            $file = $files->get($document->collection());

            if ($file !== null) {
                $list[] = ['key' => $document->value, 'label' => $document->label(), 'file' => MediaResource::make($file)];
            }
        }

        return $list;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function record(?HandoverRecord $record): ?array
    {
        if ($record === null) {
            return null;
        }

        $money = fn (?int $minor) => $minor === null ? null : new Money($minor);

        return [
            'odometer_km' => $record->odometer_km,
            'fuel_level' => $record->fuel_level->value,
            'fuel_label' => $record->fuel_level->label(),
            'balance_received' => $money($record->balance_received_minor),
            'deposit_received' => $money($record->deposit_received_minor),
            'deposit_refunded' => $money($record->deposit_refunded_minor),
            'excess_km' => $record->excess_km,
            'extra_charges' => $money($record->extra_charges_minor),
            'notes' => $record->notes,
            'recorded_by' => $record->recorder?->name,
            'recorded_at' => $record->recorded_at->toIso8601String(),
        ];
    }

    private static function mask(string $value): string
    {
        return str_repeat('•', max(0, mb_strlen($value) - 3)).mb_substr($value, -3);
    }
}
