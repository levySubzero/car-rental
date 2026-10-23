<?php

namespace App\Models;

use App\Enums\IdType;
use Carbon\CarbonImmutable;
use Database\Factories\BookingDriverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The booking's main driver (D37). Identity numbers are encrypted at rest; the id number also
 * has a keyed hash so staff can find repeat renters without decrypting every row.
 *
 * @property int $id
 * @property int $booking_id
 * @property string $first_name
 * @property string|null $second_name
 * @property string $surname
 * @property CarbonImmutable $dob
 * @property string $email
 * @property string $phone
 * @property string $nationality
 * @property bool $is_kenyan_resident
 * @property IdType $id_type
 * @property string $id_number
 * @property string $id_number_hash
 * @property string|null $kra_pin
 * @property string $licence_number
 * @property CarbonImmutable $licence_expiry
 * @property int $licence_years_declared
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Booking $booking
 */
class BookingDriver extends Model
{
    /** @use HasFactory<BookingDriverFactory> */
    use HasFactory;

    protected $fillable = [
        'first_name', 'second_name', 'surname', 'dob', 'email', 'phone', 'nationality',
        'is_kenyan_resident', 'id_type', 'id_number', 'kra_pin', 'licence_number',
        'licence_expiry', 'licence_years_declared',
    ];

    protected $hidden = ['id_number', 'id_number_hash', 'kra_pin', 'licence_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'is_kenyan_resident' => 'boolean',
            'id_type' => IdType::class,
            'id_number' => 'encrypted',
            'kra_pin' => 'encrypted',
            'licence_number' => 'encrypted',
            'licence_expiry' => 'date',
            'licence_years_declared' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $driver): void {
            if ($driver->isDirty('id_number')) {
                $driver->id_number_hash = self::hashIdNumber($driver->id_number);
            }
        });
    }

    /**
     * Keyed with the app key, so the small space of id numbers can't be brute-forced from a dump.
     */
    public static function hashIdNumber(string $idNumber): string
    {
        $normalised = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $idNumber));

        return hash_hmac('sha256', $normalised, (string) config('app.key'));
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function fullName(): string
    {
        return implode(' ', array_filter([$this->first_name, $this->second_name, $this->surname]));
    }
}
