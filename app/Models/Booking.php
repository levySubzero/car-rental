<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\BookingStep;
use App\Enums\HandoverMode;
use App\Models\Concerns\HasMedia;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A renter's booking (M8). The renter reaches it by a secret token, of which only the hash
 * is stored. On submit the quote and cancellation tiers are frozen into snapshots (D8, D34),
 * so later setting, rate or tier changes never alter it. Status changes go through
 * BookingStatusService and are logged as `booking-status` activity (D36).
 *
 * @property int $id
 * @property string|null $reference
 * @property string $access_token_hash
 * @property int $car_id
 * @property BookingStatus $status
 * @property CarbonImmutable $pickup_at
 * @property CarbonImmutable $return_at
 * @property HandoverMode $pickup_mode
 * @property HandoverMode $return_mode
 * @property int|null $delivery_zone_id
 * @property string|null $delivery_address
 * @property string|null $delivery_landmark
 * @property string|null $delivery_lat
 * @property string|null $delivery_lng
 * @property string|null $delivery_contact_phone
 * @property string|null $return_address
 * @property string|null $return_landmark
 * @property string|null $return_lat
 * @property string|null $return_lng
 * @property string|null $return_contact_phone
 * @property list<string>|null $extras
 * @property string|null $flight_number
 * @property string|null $display_currency
 * @property array<string, mixed>|null $quote
 * @property list<array{hours_before_pickup: int, refund_percent_bp: int, label: string|null}>|null $cancellation_tiers
 * @property int|null $total_minor
 * @property int|null $reservation_minor
 * @property int|null $balance_minor
 * @property int|null $security_deposit_minor
 * @property int|null $reservation_paid_minor
 * @property int|null $policy_bundle_id
 * @property CarbonImmutable|null $accepted_terms_at
 * @property string|null $accepted_terms_ip
 * @property BookingStep $current_step
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Car $car
 * @property-read BookingDriver|null $driver
 * @property-read DeliveryZone|null $deliveryZone
 * @property-read PolicyBundle|null $policyBundle
 * @property-read PaymentSubmission|null $latestPayment
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, HasMedia;

    protected $fillable = [
        'car_id', 'pickup_at', 'return_at', 'pickup_mode', 'return_mode', 'delivery_zone_id',
        'delivery_address', 'delivery_landmark', 'delivery_lat', 'delivery_lng', 'delivery_contact_phone',
        'return_address', 'return_landmark', 'return_lat', 'return_lng', 'return_contact_phone',
        'extras', 'flight_number', 'display_currency', 'current_step',
    ];

    protected $attributes = [
        'status' => 'draft',
        'pickup_mode' => 'office',
        'return_mode' => 'office',
        'current_step' => 'trip',
    ];

    protected $hidden = ['access_token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'pickup_at' => 'datetime',
            'return_at' => 'datetime',
            'pickup_mode' => HandoverMode::class,
            'return_mode' => HandoverMode::class,
            'extras' => 'array',
            'quote' => 'array',
            'cancellation_tiers' => 'array',
            'total_minor' => 'integer',
            'reservation_minor' => 'integer',
            'balance_minor' => 'integer',
            'security_deposit_minor' => 'integer',
            'reservation_paid_minor' => 'integer',
            'accepted_terms_at' => 'datetime',
            'current_step' => BookingStep::class,
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class)->withTrashed();
    }

    /**
     * @return HasOne<BookingDriver, $this>
     */
    public function driver(): HasOne
    {
        return $this->hasOne(BookingDriver::class);
    }

    /**
     * @return BelongsTo<DeliveryZone, $this>
     */
    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    /**
     * @return BelongsTo<PolicyBundle, $this>
     */
    public function policyBundle(): BelongsTo
    {
        return $this->belongsTo(PolicyBundle::class);
    }

    /**
     * @return HasMany<AvailabilityBlock, $this>
     */
    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }

    /**
     * @return HasMany<PaymentSubmission, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    /**
     * @return HasMany<BookingRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    /**
     * @return HasOne<PaymentSubmission, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(PaymentSubmission::class)->latestOfMany();
    }

    public function range(): DateRange
    {
        return new DateRange($this->pickup_at, $this->return_at);
    }

    public function isDraft(): bool
    {
        return $this->status === BookingStatus::Draft;
    }

    public function needsDeliveryZone(): bool
    {
        return $this->pickup_mode === HandoverMode::Address || $this->return_mode === HandoverMode::Address;
    }
}
