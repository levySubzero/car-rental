<?php

namespace App\Models;

use App\Enums\PaymentMethodCode;
use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay a booking's reservation (M9, PRD P-2–P-4). The renter gives the method,
 * the transaction message and an optional proof; staff then verify it with the reference and
 * the amount received, or reject it with a reason. Cash submissions carry no message.
 *
 * @property int $id
 * @property int $booking_id
 * @property PaymentMethodCode $payment_method_code
 * @property string|null $transaction_message
 * @property int|null $proof_media_id
 * @property PaymentStatus $status
 * @property int|null $amount_minor
 * @property string|null $reference
 * @property string|null $note
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property int|null $rejected_by
 * @property CarbonImmutable|null $rejected_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Booking $booking
 * @property-read PaymentMethod|null $method
 * @property-read Media|null $proof
 * @property-read User|null $verifier
 * @property-read User|null $rejecter
 */
class PaymentSubmission extends Model
{
    /** @use HasFactory<PaymentSubmissionFactory> */
    use HasFactory;

    protected $fillable = ['booking_id', 'payment_method_code', 'transaction_message', 'proof_media_id'];

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method_code' => PaymentMethodCode::class,
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
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
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_code', 'code');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function proof(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'proof_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function isCash(): bool
    {
        return $this->payment_method_code === PaymentMethodCode::Cash;
    }
}
