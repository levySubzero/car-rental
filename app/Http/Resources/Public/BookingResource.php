<?php

namespace App\Http\Resources\Public;

use App\Enums\BookingDocument;
use App\Http\Resources\Media\MediaResource;
use App\Models\Booking;
use App\Models\BookingDriver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A booking as its renter sees it, by token. Identity numbers and the KRA PIN never leave
 * the server: the form gets a masked hint instead.
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
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'current_step' => $this->current_step->value,
            'car' => CarCardResource::make($this->car),
            'pickup_at' => $this->pickup_at->toIso8601String(),
            'return_at' => $this->return_at->toIso8601String(),
            'pickup_mode' => $this->pickup_mode->value,
            'return_mode' => $this->return_mode->value,
            'delivery_zone_id' => $this->delivery_zone_id,
            'delivery_zone' => $this->whenLoaded('deliveryZone', fn () => $this->deliveryZone?->name),
            'delivery_address' => $this->delivery_address,
            'delivery_landmark' => $this->delivery_landmark,
            'delivery_lat' => $this->delivery_lat,
            'delivery_lng' => $this->delivery_lng,
            'delivery_contact_phone' => $this->delivery_contact_phone,
            'return_address' => $this->return_address,
            'return_landmark' => $this->return_landmark,
            'return_lat' => $this->return_lat,
            'return_lng' => $this->return_lng,
            'return_contact_phone' => $this->return_contact_phone,
            'extras' => $this->extras ?? [],
            'flight_number' => $this->flight_number,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? $this->driverData($this->driver) : null),
            'documents' => $this->when($this->isDraft(), fn () => $this->documents()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function driverData(BookingDriver $driver): array
    {
        return [
            'first_name' => $driver->first_name,
            'second_name' => $driver->second_name,
            'surname' => $driver->surname,
            'dob' => $driver->dob->toDateString(),
            'email' => $driver->email,
            'phone' => $driver->phone,
            'nationality' => $driver->nationality,
            'is_kenyan_resident' => $driver->is_kenyan_resident,
            'id_type' => $driver->id_type->value,
            'id_number_hint' => self::mask($driver->id_number),
            'kra_pin_hint' => $driver->kra_pin === null ? null : self::mask($driver->kra_pin),
            'licence_number_hint' => self::mask($driver->licence_number),
            'licence_expiry' => $driver->licence_expiry->toDateString(),
            'licence_years_declared' => $driver->licence_years_declared,
        ];
    }

    /**
     * The attached file per document, by BookingDocument value.
     *
     * @return array<string, MediaResource|null>
     */
    private function documents(): array
    {
        $files = $this->media()->where('collection', 'like', 'booking.%')->get()->keyBy('collection');

        $documents = [];

        foreach (BookingDocument::cases() as $document) {
            $file = $files->get($document->collection());
            $documents[$document->value] = $file ? MediaResource::make($file) : null;
        }

        return $documents;
    }

    private static function mask(string $value): string
    {
        return str_repeat('•', max(0, mb_strlen($value) - 3)).mb_substr($value, -3);
    }
}
