<?php

namespace App\Models;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingRequestType;
use Carbon\CarbonImmutable;
use Database\Factories\BookingRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A renter's cancellation or change request, sent from the booking portal (M10B). Staff
 * handle it by hand and mark it handled with a note; nothing changes the booking itself.
 *
 * @property int $id
 * @property int $booking_id
 * @property BookingRequestType $type
 * @property string $message
 * @property BookingRequestStatus $status
 * @property string|null $staff_note
 * @property int|null $handled_by
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Booking $booking
 * @property-read User|null $handler
 */
class BookingRequest extends Model
{
    /** @use HasFactory<BookingRequestFactory> */
    use HasFactory;

    protected $fillable = ['type', 'message'];

    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BookingRequestType::class,
            'status' => BookingRequestStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOpen(): bool
    {
        return $this->status === BookingRequestStatus::Open;
    }
}
