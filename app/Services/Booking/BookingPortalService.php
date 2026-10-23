<?php

namespace App\Services\Booking;

use App\Enums\AlertKind;
use App\Enums\BookingRequestType;
use App\Enums\BookingStatus;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Notifications\NewBookingRequest;
use App\Notifications\PortalCode;
use App\Services\Notifications\AdminAlerts;
use App\Settings\NotificationSettings;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The renter portal (M10B): a renter types the email or phone they booked with, gets a
 * one-time code by email (always email: there is no SMS, D40), and then sees their submitted
 * bookings. From there they open a booking (a fresh link, D39), download the voucher, or send
 * a cancellation or change request for staff to handle.
 *
 * Codes are kept hashed in the cache under the hashed identifier, five tries each.
 */
class BookingPortalService
{
    public const LOG_NAME = 'portal';

    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly BookingTokenService $tokens,
        private readonly AdminAlerts $alerts,
        private readonly NotificationSettings $notifications,
    ) {}

    /**
     * An email or a phone number, normalised; null when it is neither.
     *
     * @return array{type: 'email'|'phone', value: string}|null
     */
    public function identify(string $input): ?array
    {
        $input = trim($input);

        if (str_contains($input, '@')) {
            return filter_var($input, FILTER_VALIDATE_EMAIL) ? ['type' => 'email', 'value' => mb_strtolower($input)] : null;
        }

        $digits = PhoneNumber::digits($input);

        return strlen($digits) >= 9 && strlen($digits) <= 15 ? ['type' => 'phone', 'value' => $digits] : null;
    }

    /**
     * Submitted bookings of the driver with this email or phone, newest pick-up first.
     *
     * @param  array{type: 'email'|'phone', value: string}  $identity
     * @return Builder<Booking>
     */
    public function bookingsFor(array $identity): Builder
    {
        $column = $identity['type'] === 'email' ? 'email_normalized' : 'phone_digits';

        return Booking::query()
            ->where('status', '!=', BookingStatus::Draft)
            ->whereNotNull('reference')
            ->whereHas('driver', fn (Builder $driver) => $driver->where($column, $identity['value']))
            ->orderByDesc('pickup_at');
    }

    /**
     * Email a code to every address on the matching bookings. Nothing happens, and nothing
     * tells the caller, when there are none, so the form can't be used to probe who booked.
     *
     * @param  array{type: 'email'|'phone', value: string}  $identity
     */
    public function sendCode(array $identity): void
    {
        $emails = $this->bookingsFor($identity)->with('driver')->get()
            ->map(fn (Booking $booking) => $booking->driver?->email)
            ->filter()
            ->unique(fn (string $email) => mb_strtolower($email))
            ->values();

        if ($emails->isEmpty()) {
            return;
        }

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($identity), ['code' => $this->hashCode($code), 'attempts' => 0], now()->addMinutes(self::CODE_MINUTES));

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify(new PortalCode($code, self::CODE_MINUTES));
        }
    }

    /**
     * Check a code. A code works once; after five wrong tries it is gone.
     *
     * @param  array{type: 'email'|'phone', value: string}  $identity
     */
    public function verify(array $identity, string $code, ?string $ip = null): void
    {
        $key = $this->cacheKey($identity);
        /** @var array{code: string, attempts: int}|null $entry */
        $entry = Cache::get($key);

        if ($entry === null) {
            throw ValidationException::withMessages(['code' => 'That code is wrong or has expired. Please ask for a new one.']);
        }

        if (! hash_equals($entry['code'], $this->hashCode(trim($code)))) {
            $entry['attempts']++;
            $entry['attempts'] >= self::MAX_ATTEMPTS
                ? Cache::forget($key)
                : Cache::put($key, $entry, now()->addMinutes(self::CODE_MINUTES));

            throw ValidationException::withMessages(['code' => 'That code is wrong or has expired. Please ask for a new one.']);
        }

        Cache::forget($key);

        foreach ($this->bookingsFor($identity)->get() as $booking) {
            activity(self::LOG_NAME)->performedOn($booking)
                ->withProperties(['via' => $identity['type'], 'ip' => $ip])
                ->event('signed-in')
                ->log('renter signed in to the portal');
        }
    }

    /**
     * One of this renter's bookings by its reference; anyone else's is not found.
     *
     * @param  array{type: 'email'|'phone', value: string}  $identity
     */
    public function find(array $identity, string $reference): Booking
    {
        return $this->bookingsFor($identity)->where('reference', $reference)->first()
            ?? throw (new ModelNotFoundException)->setModel(Booking::class, [$reference]);
    }

    /**
     * A fresh link to the booking page. The renter's older links stop working (D39).
     */
    public function open(Booking $booking): string
    {
        return $this->tokens->rotate($booking);
    }

    public function acceptsRequests(Booking $booking): bool
    {
        return ! $booking->status->isTerminal();
    }

    public function request(Booking $booking, BookingRequestType $type, string $message): BookingRequest
    {
        if (! $this->acceptsRequests($booking)) {
            throw BookingException::requestsClosed();
        }

        $request = DB::transaction(function () use ($booking, $type, $message) {
            $request = $booking->requests()->create(['type' => $type, 'message' => trim($message)]);

            activity(self::LOG_NAME)->performedOn($booking)
                ->withProperties(['request' => $request->id, 'type' => $type->value])
                ->event('requested')
                ->log("{$type->label()} requested");

            return $request;
        });

        if ($this->notifications->staff_emails !== []) {
            Notification::route('mail', $this->notifications->staff_emails)->notify(new NewBookingRequest($request));
        }

        $this->alerts->send(
            AlertKind::BookingRequest,
            "{$type->label()} request for {$booking->reference}",
            Str::limit($request->message, 120),
            route('admin.booking-requests.index'),
        );

        return $request;
    }

    /**
     * @param  array{type: 'email'|'phone', value: string}  $identity
     * @return Collection<int, Booking>
     */
    public function list(array $identity): Collection
    {
        return $this->bookingsFor($identity)->with(['car', 'requests' => fn ($query) => $query->latest('id')])->get();
    }

    /**
     * @param  array{type: 'email'|'phone', value: string}  $identity
     */
    private function cacheKey(array $identity): string
    {
        return 'portal-code:'.hash('sha256', $identity['type'].':'.$identity['value']);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
