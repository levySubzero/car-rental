<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePortalSession;
use App\Http\Requests\Public\Portal\SendPortalCodeRequest;
use App\Http\Requests\Public\Portal\StoreBookingRequestRequest;
use App\Http\Requests\Public\Portal\VerifyPortalCodeRequest;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Services\Booking\BookingPortalService;
use App\Services\Media\MediaService;
use App\Services\Notifications\VoucherService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `/my-bookings` (M10B): renters find their bookings with a code emailed to the address on
 * them, then open one, download its voucher or send a request. See BookingPortalService.
 */
class BookingPortalController extends Controller
{
    private const PENDING_KEY = 'portal_pending';

    public function __construct(private readonly BookingPortalService $portal) {}

    public function index(Request $request): Response
    {
        $identity = EnsurePortalSession::identity($request);

        if ($identity === null) {
            return Inertia::render('public/my-bookings/index', [
                'step' => $request->session()->has(self::PENDING_KEY) ? 'code' : 'identify',
                'identifier' => $request->session()->get(self::PENDING_KEY.'.typed'),
                'turnstile_site_key' => config('services.turnstile.site_key'),
            ]);
        }

        return Inertia::render('public/my-bookings/list', [
            'identifier' => $identity['value'],
            'bookings' => $this->portal->list($identity)->map(fn (Booking $booking) => $this->row($booking))->values(),
        ]);
    }

    public function sendCode(SendPortalCodeRequest $request): RedirectResponse
    {
        $identity = $request->identity();
        $this->portal->sendCode($identity);

        $request->session()->put(self::PENDING_KEY, [...$identity, 'typed' => trim((string) $request->validated('identifier'))]);

        return to_route('portal.index');
    }

    public function verify(VerifyPortalCodeRequest $request): RedirectResponse
    {
        /** @var array{type: 'email'|'phone', value: string}|null $pending */
        $pending = $request->session()->get(self::PENDING_KEY);

        if ($pending === null) {
            return to_route('portal.index');
        }

        $identity = ['type' => $pending['type'], 'value' => $pending['value']];
        $this->portal->verify($identity, (string) $request->validated('code'), $request->ip());

        $request->session()->forget(self::PENDING_KEY);
        $request->session()->regenerate();
        $request->session()->put(EnsurePortalSession::SESSION_KEY, [
            ...$identity,
            'expires_at' => now()->addMinutes(EnsurePortalSession::MINUTES)->getTimestamp(),
        ]);

        return to_route('portal.index');
    }

    public function restart(Request $request): RedirectResponse
    {
        $request->session()->forget([self::PENDING_KEY, EnsurePortalSession::SESSION_KEY]);

        return to_route('portal.index');
    }

    public function open(Request $request, string $reference): RedirectResponse
    {
        $booking = $this->portal->find($this->identity($request), $reference);

        return to_route('bookings.show', $this->portal->open($booking));
    }

    public function voucher(Request $request, string $reference, VoucherService $vouchers, MediaService $media): StreamedResponse
    {
        $booking = $this->portal->find($this->identity($request), $reference);
        $voucher = $vouchers->current($booking) ?? abort(404);

        $media->logAccess($voucher, 'downloaded', null, $request->ip());
        $file = $media->open($voucher);

        return response()->stream(function () use ($file): void {
            fpassthru($file['stream']);
            fclose($file['stream']);
        }, 200, [
            'Content-Type' => $file['mime'],
            'Content-Length' => (string) $file['size'],
            'Content-Disposition' => 'attachment; filename="'.$file['name'].'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function storeRequest(StoreBookingRequestRequest $request, string $reference): RedirectResponse
    {
        $booking = $this->portal->find($this->identity($request), $reference);
        $this->portal->request($booking, $request->type(), (string) $request->validated('message'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Request sent. Our team will get back to you.']);

        return to_route('portal.index');
    }

    /**
     * @return array{type: 'email'|'phone', value: string}
     */
    private function identity(Request $request): array
    {
        return EnsurePortalSession::identity($request) ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Booking $booking): array
    {
        return [
            'reference' => $booking->reference,
            'car' => $booking->car->name,
            'pickup_at' => $booking->pickup_at->toIso8601String(),
            'return_at' => $booking->return_at->toIso8601String(),
            'status' => $booking->status->value,
            'status_label' => $booking->status->label(),
            'total' => new Money((int) $booking->total_minor),
            'balance' => new Money((int) $booking->balance_minor),
            'has_voucher' => $booking->primaryMedia(VoucherService::COLLECTION)->exists(),
            'accepts_requests' => $this->portal->acceptsRequests($booking),
            'requests' => $booking->requests->map(fn (BookingRequest $request) => [
                'id' => $request->id,
                'type_label' => $request->type->label(),
                'status' => $request->status->value,
                'status_label' => $request->status->label(),
                'created_at' => $request->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
