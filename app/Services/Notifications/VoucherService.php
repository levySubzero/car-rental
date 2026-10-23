<?php

namespace App\Services\Notifications;

use App\Enums\HandoverMode;
use App\Models\Booking;
use App\Models\Media;
use App\Services\Media\MediaService;
use App\Settings\GeneralSettings;
use App\Settings\NotificationSettings;
use App\Support\Money;
use App\Support\WhatsAppLinkBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;

/**
 * The booking voucher (M10.6, PRD P-6): a PDF rendered with dompdf and stored as the
 * booking's private `booking.voucher` file. Every figure comes from the booking's own
 * snapshot, so later price, rate or tier changes never alter it. Regenerating replaces it.
 */
class VoucherService
{
    public const COLLECTION = 'booking.voucher';

    public function __construct(
        private readonly MediaService $media,
        private readonly GeneralSettings $general,
        private readonly NotificationSettings $notifications,
    ) {}

    public function generate(Booking $booking, ?Model $actor = null): Media
    {
        return $this->media->storeGenerated(
            $booking,
            self::COLLECTION,
            Pdf::loadHTML($this->html($booking))->setPaper('a4')->output(),
            "Voucher {$booking->reference}.pdf",
            'application/pdf',
            $actor,
        );
    }

    public function current(Booking $booking): ?Media
    {
        return $booking->primaryMedia(self::COLLECTION)->first();
    }

    public function html(Booking $booking): string
    {
        return View::make('pdf.voucher', [
            'voucher' => $this->fields($booking),
            'company' => $this->general,
        ])->render();
    }

    /**
     * The P-6 fields, formatted for display.
     *
     * @return array{
     *     reference: string|null,
     *     renter: string,
     *     car: string,
     *     days: int|null,
     *     total: string,
     *     reservation_paid: string,
     *     balance: string,
     *     security_deposit: string|null,
     *     delivery_pending: bool,
     *     pickup: array{date: string, time: string, point: string},
     *     return: array{date: string, time: string, point: string},
     *     tiers: list<array{label: string, refund: string}>,
     * }
     */
    public function fields(Booking $booking): array
    {
        $booking->loadMissing(['car', 'driver', 'deliveryZone']);
        $driver = $booking->driver;
        $quote = $booking->quote ?? [];

        return [
            'reference' => $booking->reference,
            'renter' => $driver === null ? '' : implode(' ', array_filter([$driver->first_name, $driver->second_name, $driver->surname])),
            'car' => $booking->car->name,
            'days' => isset($quote['days']) ? (int) $quote['days'] : null,
            'total' => $this->money($booking->total_minor),
            'reservation_paid' => $this->money($booking->reservation_paid_minor),
            'balance' => $this->money($booking->balance_minor),
            'security_deposit' => $booking->security_deposit_minor === null ? null : $this->money($booking->security_deposit_minor),
            'delivery_pending' => (bool) ($quote['requires_staff_quote'] ?? false),
            'pickup' => $this->handover($booking->pickup_at, $booking->pickup_mode, $booking->delivery_address, $booking->delivery_landmark, $booking),
            'return' => $this->handover($booking->return_at, $booking->return_mode, $booking->return_address, $booking->return_landmark, $booking),
            'tiers' => array_map(fn (array $tier) => [
                'label' => $tier['label'] ?? "{$tier['hours_before_pickup']} hours or more before pick-up",
                'refund' => rtrim(rtrim(number_format($tier['refund_percent_bp'] / 100, 2), '0'), '.').'%',
            ], $booking->cancellation_tiers ?? []),
        ];
    }

    /**
     * The WhatsApp confirmation (P-5): the template from Notification settings.
     */
    public function whatsAppText(Booking $booking): string
    {
        $booking->loadMissing('car');
        $zone = (string) config('baines.timezone');

        return WhatsAppLinkBuilder::fill($this->notifications->voucher_whatsapp_text, [
            'reference' => $booking->reference,
            'car' => $booking->car->name,
            'dates' => $booking->pickup_at->setTimezone($zone)->format('j M Y').' to '.$booking->return_at->setTimezone($zone)->format('j M Y'),
        ]);
    }

    /**
     * @return array{date: string, time: string, point: string}
     */
    private function handover(CarbonImmutable $at, HandoverMode $mode, ?string $address, ?string $landmark, Booking $booking): array
    {
        $local = $at->setTimezone((string) config('baines.timezone'));

        $point = $mode === HandoverMode::Office
            ? trim($this->general->company_name.' office'.($this->general->address ? ', '.$this->general->address : ''))
            : implode(', ', array_filter([$address, $landmark, $booking->deliveryZone?->name]));

        return ['date' => $local->format('l j F Y'), 'time' => $local->format('H:i'), 'point' => $point];
    }

    private function money(?int $minor): string
    {
        return (new Money((int) $minor))->format();
    }
}
