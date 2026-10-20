<?php

namespace App\Models;

use App\Enums\BlackoutReason;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Database\Factories\CarBlackoutFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A period a car is off the road (maintenance, private use). The availability check (M8)
 * treats it like a booking. Periods are half-open, like DateRange.
 *
 * @property int $id
 * @property int $car_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property BlackoutReason $reason
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Car $car
 */
class CarBlackout extends Model
{
    /** @use HasFactory<CarBlackoutFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['starts_at', 'ends_at', 'reason', 'notes', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reason' => BlackoutReason::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('availability')
            ->logOnly(['car_id', 'starts_at', 'ends_at', 'reason', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
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
     * Blackouts that overlap the half-open $range.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, DateRange $range): void
    {
        $query->where('starts_at', '<', $range->end)->where('ends_at', '>', $range->start);
    }
}
