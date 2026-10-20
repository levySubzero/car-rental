<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A message from the public contact form (M7.15), worked through in the admin inbox.
 *
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string $message
 * @property int|null $car_id
 * @property string|null $preferred_dates
 * @property ContactMessageStatus $status
 * @property int|null $handled_by
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Car|null $car
 * @property-read User|null $handler
 */
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'email', 'phone', 'message', 'car_id', 'preferred_dates', 'status'];

    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Only the workflow is logged; the message itself may hold personal data.
        return LogOptions::defaults()
            ->useLogName('contact-messages')
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
