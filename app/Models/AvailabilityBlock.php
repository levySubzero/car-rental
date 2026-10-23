<?php

namespace App\Models;

use App\Enums\AvailabilityBlockType;
use App\Enums\BlackoutReason;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Database\Factories\AvailabilityBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A period a car cannot be booked (D35): a booking's hold, a reserved booking, or a staff
 * block (maintenance, private use). AvailabilityService reads only this table. Periods are
 * half-open, like DateRange.
 *
 * @property int $id
 * @property int $car_id
 * @property AvailabilityBlockType $type
 * @property int|null $booking_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $expires_at
 * @property BlackoutReason|null $reason
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Car $car
 * @property-read Booking|null $booking
 */
class AvailabilityBlock extends Model
{
    /** @use HasFactory<AvailabilityBlockFactory> */
    use HasFactory, LogsActivity {
        shouldLogEvent as shouldLogActivityEvent;
    }

    protected $table = 'car_availability_blocks';

    protected $fillable = ['car_id', 'type', 'booking_id', 'starts_at', 'ends_at', 'expires_at', 'reason', 'notes', 'created_by'];

    protected $attributes = [
        'type' => 'blocked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AvailabilityBlockType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'reason' => BlackoutReason::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Holds come and go with bookings, which log their own status; only staff blocks are logged.
        return LogOptions::defaults()
            ->useLogName('availability')
            ->logOnly(['car_id', 'starts_at', 'ends_at', 'reason', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        return $this->type === AvailabilityBlockType::Blocked && $this->shouldLogActivityEvent($eventName);
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function range(): DateRange
    {
        return new DateRange($this->starts_at, $this->ends_at);
    }

    /**
     * Blocks that overlap the half-open $range.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, DateRange $range): void
    {
        $query->where('starts_at', '<', $range->end)->where('ends_at', '>', $range->start);
    }

    /**
     * Blocks that still stop a booking: everything except holds past their expiry.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();

        $query->where(fn (Builder $query) => $query
            ->where('type', '!=', AvailabilityBlockType::Hold->value)
            ->orWhere('expires_at', '>', $at));
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeStaffBlocks(Builder $query): void
    {
        $query->where('type', AvailabilityBlockType::Blocked->value);
    }
}
