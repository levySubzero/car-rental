<?php

namespace App\Services\Payments;

use App\Enums\AlertKind;
use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethodCode;
use App\Enums\PaymentStatus;
use App\Exceptions\Domain\PaymentException;
use App\Models\Booking;
use App\Models\GatewayTransaction;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Notifications\AdminAlerts;
use App\Services\Payments\Gateways\DarajaClient;
use App\Services\Payments\Gateways\GatewayException;
use App\Services\Payments\Gateways\PaystackClient;
use App\Services\Payments\Gateways\StkQueryResult;
use App\Settings\GatewaySettings;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Paying the reservation online (M11B.4, D42): an M-Pesa STK push or a Paystack card payment
 * started from the booking page, then settled once the gateway confirms the money.
 *
 * Starting refuses unless the booking takes a payment, none is waiting for staff and the
 * method is online; it gives the hold at least `HOLD_MINUTES` so the car stays put while the
 * renter approves. Settling applies money only when it is safe to: the full reservation, on a
 * booking that still takes a payment, whose car is still free. Anything else waits for staff
 * as needs review (or unmatched, for a paybill reference that names no booking); nothing is
 * refunded or guessed automatically.
 */
class OnlinePaymentService
{
    public const HOLD_MINUTES = 10;

    // A second STK push this soon after the first is refused, so a double tap prompts once.
    private const STK_COOLDOWN_SECONDS = 60;

    // Reconcile asks about attempts this old, and gives up on them after the second.
    public const RECONCILE_AFTER_MINUTES = 2;

    public const GIVE_UP_AFTER_MINUTES = 30;

    public function __construct(
        private readonly DarajaClient $daraja,
        private readonly PaystackClient $paystack,
        private readonly PaymentVerificationService $verification,
        private readonly AvailabilityService $availability,
        private readonly AdminAlerts $alerts,
        private readonly GatewaySettings $gatewaySettings,
    ) {}

    /**
     * The method takes payments online: its gateway has keys, and staff have it active and
     * switched online. Bank and cash are always manual.
     */
    public function isOnline(PaymentMethod $method): bool
    {
        return $method->is_active && $method->online_enabled && $this->gatewayConfigured($method->code);
    }

    /**
     * The method can go online (M-Pesa through Daraja, card through Paystack) and its keys
     * are in the environment.
     */
    public function gatewayConfigured(PaymentMethodCode $code): bool
    {
        return match ($code) {
            PaymentMethodCode::Mpesa => $this->daraja->configured(),
            PaymentMethodCode::Card => $this->paystack->configured(),
            default => false,
        };
    }

    /**
     * The renter's latest STK push or card payment from the last half hour, which the booking
     * page follows while it is in flight.
     */
    public function latestAttempt(Booking $booking): ?GatewayTransaction
    {
        return $booking->gatewayTransactions()
            ->whereIn('gateway', [PaymentGateway::MpesaStk, PaymentGateway::Paystack])
            ->where('created_at', '>=', now()->subMinutes(self::GIVE_UP_AFTER_MINUTES))
            ->latest('id')
            ->first();
    }

    /**
     * Where to pay by hand with M-Pesa: the paybill and the account number that matches the
     * booking (C2B), or the till. Null when Daraja is not configured.
     *
     * @return array{number: string, account: string|null, till: bool}|null
     */
    public function mpesaPayTo(Booking $booking): ?array
    {
        $number = $this->daraja->payTo();

        if (! $this->daraja->configured() || $number === null) {
            return null;
        }

        return [
            'number' => $number,
            'account' => $this->daraja->isTill() ? null : $booking->compactReference(),
            'till' => $this->daraja->isTill(),
        ];
    }

    public function startMpesa(Booking $booking, string $phone): GatewayTransaction
    {
        $msisdn = PhoneNumber::kenyanMobile($phone) ?? throw PaymentException::invalidMpesaPhone();
        $booking = $this->prepare($booking, PaymentMethodCode::Mpesa, function (Booking $booking) {
            $recent = $booking->gatewayTransactions()
                ->where('gateway', PaymentGateway::MpesaStk)
                ->where('status', GatewayTransactionStatus::Initiated)
                ->where('created_at', '>', now()->subSeconds(self::STK_COOLDOWN_SECONDS))
                ->exists();

            if ($recent) {
                throw PaymentException::stkJustSent();
            }
        });

        try {
            $push = $this->daraja->stkPush(
                $msisdn,
                (int) $booking->reservation_minor,
                (string) $booking->compactReference(),
                'Booking '.$booking->compactReference(),
                $this->stkCallbackUrl(),
            );
        } catch (GatewayException $exception) {
            report($exception);

            throw PaymentException::gatewayUnavailable('M-Pesa');
        }

        return $booking->gatewayTransactions()->create([
            'gateway' => PaymentGateway::MpesaStk,
            'external_ref' => $push->checkoutRequestId,
            'amount_minor' => (int) $booking->reservation_minor,
            'payer_phone' => $msisdn,
            'status' => GatewayTransactionStatus::Initiated,
            'payload' => ['merchant_request_id' => $push->merchantRequestId],
        ]);
    }

    /**
     * @return array{access_code: string, reference: string, public_key: string}
     */
    public function startCard(Booking $booking): array
    {
        $booking = $this->prepare($booking, PaymentMethodCode::Card);
        $email = $booking->driver->email;

        if (blank($email)) {
            throw PaymentException::noRenterEmail();
        }

        $reference = $booking->compactReference().'-'.Str::upper(Str::random(8));

        try {
            $accessCode = $this->paystack->initialize($email, (int) $booking->reservation_minor, $reference, [
                'booking' => (string) $booking->reference,
            ]);
        } catch (GatewayException $exception) {
            report($exception);

            throw PaymentException::gatewayUnavailable('Card payment');
        }

        $booking->gatewayTransactions()->create([
            'gateway' => PaymentGateway::Paystack,
            'external_ref' => $reference,
            'amount_minor' => (int) $booking->reservation_minor,
            'status' => GatewayTransactionStatus::Initiated,
        ]);

        return ['access_code' => $accessCode, 'reference' => $reference, 'public_key' => (string) $this->paystack->publicKey()];
    }

    /**
     * The popup says the card was charged: ask Paystack, never the browser.
     */
    public function confirmCard(Booking $booking, string $reference): GatewayTransaction
    {
        $transaction = $booking->gatewayTransactions()
            ->where('gateway', PaymentGateway::Paystack)
            ->where('external_ref', $reference)
            ->first() ?? throw PaymentException::unknownOnlinePayment();

        return $this->refreshPaystack($transaction);
    }

    /**
     * Ask Daraja how an STK push ended and act on it. A push still waiting on the phone is
     * left alone. One already given up on (failed) is asked again too, so a genuine payment
     * reported late is never dropped.
     */
    public function refreshStk(GatewayTransaction $transaction, ?string $receipt = null): GatewayTransaction
    {
        if (! $this->stillOpen($transaction)) {
            return $transaction;
        }

        $result = $this->daraja->stkQuery($transaction->external_ref);

        return match ($result->state) {
            StkQueryResult::PAID => $this->markPaid($transaction, $receipt),
            StkQueryResult::FAILED => $this->markFailed($transaction, $result->description),
            default => $transaction,
        };
    }

    public function refreshPaystack(GatewayTransaction $transaction): GatewayTransaction
    {
        if (! $this->stillOpen($transaction)) {
            return $transaction;
        }

        $result = $this->paystack->verify($transaction->external_ref);

        if ($result->isPending()) {
            return $transaction;
        }

        if (! $result->isSuccessful()) {
            return $this->markFailed($transaction, $result->gatewayResponse ?? $result->status);
        }

        // Paystack charges what we initialized; anything else is not ours to apply.
        if ($result->currency !== 'KES' || $result->amountMinor !== $transaction->amount_minor) {
            $transaction->forceFill(['amount_minor' => $result->amountMinor])->save();

            return $this->needsReview($this->markPaid($transaction, $result->id, settle: false), "Paystack charged {$result->currency} {$result->amountMinor} (minor units), not the amount asked.");
        }

        return $this->markPaid($transaction, $result->id);
    }

    /**
     * Settle attempts the gateways never reported back on (M11B.6): each push or card payment
     * older than `RECONCILE_AFTER_MINUTES` is asked about; one still unanswered after
     * `GIVE_UP_AFTER_MINUTES` is marked failed. A gateway that cannot be reached is skipped and
     * tried on the next run.
     *
     * @return array{checked: int, paid: int, failed: int, errors: int}
     */
    public function reconcile(): array
    {
        $counts = ['checked' => 0, 'paid' => 0, 'failed' => 0, 'errors' => 0];

        GatewayTransaction::query()
            ->whereIn('gateway', [PaymentGateway::MpesaStk, PaymentGateway::Paystack])
            ->where('status', GatewayTransactionStatus::Initiated)
            ->where('created_at', '<=', now()->subMinutes(self::RECONCILE_AFTER_MINUTES))
            ->orderBy('id')
            ->each(function (GatewayTransaction $transaction) use (&$counts) {
                $counts['checked']++;

                try {
                    $transaction = $transaction->gateway === PaymentGateway::Paystack
                        ? $this->refreshPaystack($transaction)
                        : $this->refreshStk($transaction);
                } catch (GatewayException $exception) {
                    report($exception);
                    $counts['errors']++;
                }

                if ($transaction->status === GatewayTransactionStatus::Initiated
                    && $transaction->created_at?->isBefore(now()->subMinutes(self::GIVE_UP_AFTER_MINUTES))) {
                    $transaction = $this->markFailed($transaction, 'No answer from the gateway within '.self::GIVE_UP_AFTER_MINUTES.' minutes.');
                }

                match ($transaction->status) {
                    GatewayTransactionStatus::Initiated => null,
                    GatewayTransactionStatus::Failed => $counts['failed']++,
                    default => $counts['paid']++,
                };
            });

        return $counts;
    }

    /**
     * Tell Safaricom where to send paybill confirmations and remember it for the before-launch
     * checklist (M11B.6).
     */
    public function registerC2b(): void
    {
        $secret = (string) $this->daraja->callbackSecret();

        $this->daraja->registerC2bUrls(
            route('hooks.mobile.c2b.confirm', ['secret' => $secret]),
            route('hooks.mobile.c2b.validate', ['secret' => $secret]),
        );

        $this->gatewaySettings->c2b_registered_at = now()->toIso8601String();
        $this->gatewaySettings->c2b_registered_shortcode = $this->daraja->payTo();
        $this->gatewaySettings->save();
    }

    /**
     * C2B was registered, for the paybill or till configured now.
     */
    public function c2bRegistered(): bool
    {
        return $this->gatewaySettings->c2b_registered_at !== null
            && $this->gatewaySettings->c2b_registered_shortcode === $this->daraja->payTo();
    }

    /**
     * Apply paid money to its booking, or hand it to staff. Safe to call again: only money
     * not yet applied is looked at. Staff resolving a receipt (M11B.9) pass themselves.
     *
     * @param  User|'system'  $actor
     */
    public function settle(GatewayTransaction $transaction, User|string $actor = 'system'): GatewayTransaction
    {
        $review = null;

        $transaction = DB::transaction(function () use ($transaction, $actor, &$review) {
            $locked = GatewayTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [GatewayTransactionStatus::Paid, GatewayTransactionStatus::NeedsReview, GatewayTransactionStatus::Unmatched], true)) {
                return $locked;
            }

            $booking = $locked->booking;

            if ($booking === null) {
                $locked->forceFill(['status' => GatewayTransactionStatus::Unmatched])->save();
                $review = $locked;

                return $locked;
            }

            $problem = $this->problemWith($locked, $booking);

            if ($problem !== null) {
                $review = $this->needsReview($locked, $problem, alert: false);

                return $review;
            }

            $submission = $this->verification->confirmOnline($booking, $locked, $actor);

            $locked->forceFill([
                'status' => GatewayTransactionStatus::Matched,
                'payment_submission_id' => $submission->id,
                'failure_reason' => null,
            ])->save();

            return $locked;
        });

        if ($review !== null) {
            $this->alert($review);
        }

        return $transaction;
    }

    /**
     * Why paid money cannot be applied to the booking safely, or null when it can.
     */
    private function problemWith(GatewayTransaction $transaction, Booking $booking): ?string
    {
        $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

        if (! $booking->status->acceptsPayment()) {
            return "The booking is {$booking->status->label()}, so it no longer takes a payment.";
        }

        if ($booking->payments()->where('status', PaymentStatus::Pending)->exists()) {
            return 'Another payment for this booking is waiting to be checked.';
        }

        if ($transaction->amount_minor < (int) $booking->reservation_minor) {
            return 'Less than the reservation due was paid.';
        }

        if (! $this->availability->isAvailable($booking->car, $booking->range(), $booking)) {
            return 'The hold lapsed and the car has since been taken for these dates.';
        }

        return null;
    }

    /**
     * Lock the booking and check it can be paid online by this method; extend its hold.
     *
     * @param  (callable(Booking): void)|null  $check  a method's own extra check
     */
    private function prepare(Booking $booking, PaymentMethodCode $code, ?callable $check = null): Booking
    {
        $method = PaymentMethod::query()->where('code', $code)->first();

        if ($method === null || ! $this->isOnline($method)) {
            throw PaymentException::methodUnavailable();
        }

        return DB::transaction(function () use ($booking, $check) {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->acceptsPayment()) {
                throw PaymentException::notAwaitingPayment($locked);
            }

            if ($locked->payments()->where('status', PaymentStatus::Pending)->exists()) {
                throw PaymentException::alreadySubmitted();
            }

            if ($check !== null) {
                $check($locked);
            }

            $until = now()->addMinutes(self::HOLD_MINUTES);

            if ($locked->expires_at !== null && $locked->expires_at->isBefore($until)) {
                $this->availability->extendHold($locked, $until);
            }

            return $locked;
        });
    }

    private function stillOpen(GatewayTransaction $transaction): bool
    {
        return $transaction->status === GatewayTransactionStatus::Initiated || $transaction->status === GatewayTransactionStatus::Failed;
    }

    private function markPaid(GatewayTransaction $transaction, ?string $receipt, bool $settle = true): GatewayTransaction
    {
        $updated = GatewayTransaction::query()
            ->whereKey($transaction->id)
            ->whereIn('status', [GatewayTransactionStatus::Initiated->value, GatewayTransactionStatus::Failed->value])
            ->update(['status' => GatewayTransactionStatus::Paid->value, 'receipt' => $receipt ?? $transaction->receipt, 'updated_at' => now()]);

        $transaction->refresh();

        // Another request (callback, reconcile, popup) got here first and settles it.
        if ($updated === 0) {
            return $transaction;
        }

        return $settle ? $this->settle($transaction) : $transaction;
    }

    private function markFailed(GatewayTransaction $transaction, string $reason): GatewayTransaction
    {
        GatewayTransaction::query()
            ->whereKey($transaction->id)
            ->where('status', GatewayTransactionStatus::Initiated)
            ->update(['status' => GatewayTransactionStatus::Failed->value, 'failure_reason' => Str::limit($reason, 490), 'updated_at' => now()]);

        return $transaction->refresh();
    }

    private function needsReview(GatewayTransaction $transaction, string $reason, bool $alert = true): GatewayTransaction
    {
        $transaction->forceFill(['status' => GatewayTransactionStatus::NeedsReview, 'failure_reason' => $reason])->save();

        if ($alert) {
            $this->alert($transaction);
        }

        return $transaction;
    }

    private function alert(GatewayTransaction $transaction): void
    {
        $booking = $transaction->booking;
        $money = 'KES '.number_format($transaction->amount_minor / 100);
        $receipt = $transaction->receipt ?? $transaction->external_ref;

        $this->alerts->send(
            AlertKind::PaymentNeedsReview,
            $booking === null
                ? "{$transaction->gateway->label()} payment with no booking"
                : "Payment to resolve for {$booking->reference}",
            $booking === null
                ? "{$money}, receipt {$receipt}: the account reference names no booking."
                : "{$money}, receipt {$receipt}. {$transaction->failure_reason}",
            route('admin.payments.index', $booking === null ? [] : ['search' => $booking->reference]),
        );
    }

    private function stkCallbackUrl(): string
    {
        return route('hooks.mobile.stk', ['secret' => (string) $this->daraja->callbackSecret()]);
    }
}
