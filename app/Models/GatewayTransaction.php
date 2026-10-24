<?php

namespace App\Models;

use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentGateway;
use Carbon\CarbonImmutable;
use Database\Factories\GatewayTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One online payment (M11B, D42): an STK push or Paystack attempt started from the booking
 * page, or a paybill receipt Safaricom reported. `external_ref` is the gateway's id for it
 * (CheckoutRequestID, TransID or our Paystack reference), so a repeated callback finds the
 * same row. Once paid and matched it points at the verified PaymentSubmission it created.
 *
 * @property int $id
 * @property PaymentGateway $gateway
 * @property string $external_ref
 * @property string|null $receipt
 * @property int|null $booking_id
 * @property int|null $payment_submission_id
 * @property int $amount_minor
 * @property string|null $payer_phone
 * @property string|null $payer_name
 * @property GatewayTransactionStatus $status
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $payload
 * @property int|null $resolved_by
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $resolution_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Booking|null $booking
 * @property-read PaymentSubmission|null $submission
 * @property-read User|null $resolver
 */
class GatewayTransaction extends Model
{
    /** @use HasFactory<GatewayTransactionFactory> */
    use HasFactory;

    protected $fillable = ['gateway', 'external_ref', 'receipt', 'booking_id', 'amount_minor', 'payer_phone', 'payer_name', 'status', 'payload'];

    protected $hidden = ['payer_phone', 'payload'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => GatewayTransactionStatus::class,
            'amount_minor' => 'integer',
            'payer_phone' => 'encrypted',
            'payload' => 'array',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<PaymentSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(PaymentSubmission::class, 'payment_submission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeAwaitingStaff(Builder $query): void
    {
        $query->whereIn('status', [GatewayTransactionStatus::Unmatched, GatewayTransactionStatus::NeedsReview]);
    }
}
