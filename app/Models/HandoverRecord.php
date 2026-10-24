<?php

namespace App\Models;

use App\Enums\FuelLevel;
use App\Enums\HandoverType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What staff noted when the car went out (handover, M11.6) or came back (return, M11.7).
 * A booking has at most one of each. Money is KES minor units.
 *
 * @property int $id
 * @property int $booking_id
 * @property HandoverType $type
 * @property int $odometer_km
 * @property FuelLevel $fuel_level
 * @property int|null $balance_received_minor
 * @property int|null $deposit_received_minor
 * @property int|null $deposit_refunded_minor
 * @property int|null $excess_km
 * @property int|null $extra_charges_minor
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property CarbonImmutable $recorded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Booking $booking
 * @property-read User|null $recorder
 */
class HandoverRecord extends Model
{
    protected $fillable = [
        'type', 'odometer_km', 'fuel_level', 'balance_received_minor', 'deposit_received_minor',
        'deposit_refunded_minor', 'excess_km', 'extra_charges_minor', 'notes', 'recorded_by', 'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HandoverType::class,
            'fuel_level' => FuelLevel::class,
            'odometer_km' => 'integer',
            'balance_received_minor' => 'integer',
            'deposit_received_minor' => 'integer',
            'deposit_refunded_minor' => 'integer',
            'excess_km' => 'integer',
            'extra_charges_minor' => 'integer',
            'recorded_at' => 'datetime',
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
