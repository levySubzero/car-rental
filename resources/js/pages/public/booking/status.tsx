import { Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { CancellationTerms } from '@/components/public/booking/cancellation-terms';
import { QuoteSummary } from '@/components/public/booking/quote-summary';
import { BrandIcon } from '@/components/public/icons';
import { Button } from '@/components/ui/button';
import { useSite } from '@/hooks/use-site';
import { formatBookingTime } from '@/lib/booking';
import { whatsappLink } from '@/lib/whatsapp';
import type { Booking, CancellationTier, PublicQuote } from '@/types';

/**
 * The renter's booking after submit (M8.16): its status, the frozen quote and its own
 * cancellation terms. Payment instructions join in M9.
 */
export default function BookingStatus({
    booking,
    quote,
    cancellation_tiers: tiers,
}: {
    token: string;
    booking: Booking;
    quote: PublicQuote | null;
    cancellation_tiers: CancellationTier[];
}) {
    const { site } = useSite();
    const chat = whatsappLink(
        site.whatsapp_number,
        site.whatsapp_templates.booking,
        { reference: booking.reference },
    );
    const awaitingPayment = booking.status === 'awaiting_payment';

    return (
        <>
            <Head title={`Booking ${booking.reference ?? ''}`.trim()}>
                <meta
                    head-key="robots"
                    name="robots"
                    content="noindex, nofollow"
                />
            </Head>

            <section className="tone-charcoal">
                <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    <p className="text-sm text-muted-foreground">
                        Booking {booking.reference}
                    </p>
                    <h1 className="mt-1 font-display text-3xl text-foreground sm:text-4xl">
                        {booking.car.name}
                    </h1>
                    <p className="mt-3 inline-flex items-center gap-2 rounded-full border border-gold/40 px-3 py-1 text-sm text-gold">
                        <CheckCircle2 aria-hidden className="size-4" />
                        {booking.status_label}
                    </p>
                </div>
            </section>

            <div className="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-12 lg:px-8">
                <div className="grid min-w-0 content-start gap-8 lg:col-span-7">
                    {awaitingPayment && (
                        <section className="rounded-lg border border-gold/40 bg-surface p-6">
                            <h2 className="text-lg font-semibold text-foreground">
                                Next: pay the reservation
                            </h2>
                            <p className="mt-2 text-sm text-white/80">
                                We are holding the car for you
                                {booking.expires_at &&
                                    ` until ${formatBookingTime(booking.expires_at)}`}
                                . Payment details follow; meanwhile our team can
                                help on WhatsApp or by phone.
                            </p>
                            {chat && (
                                <Button
                                    asChild
                                    className="mt-4 bg-[#25D366] font-semibold text-ink hover:bg-[#25D366]/90"
                                >
                                    <a
                                        href={chat}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <BrandIcon
                                            name="whatsapp"
                                            className="size-4"
                                        />
                                        Chat on WhatsApp
                                    </a>
                                </Button>
                            )}
                        </section>
                    )}

                    <section className="rounded-lg bg-surface p-6">
                        <h2 className="text-lg font-semibold text-foreground">
                            Trip
                        </h2>
                        <dl className="mt-4 grid gap-3 text-sm">
                            <div className="grid gap-0.5 sm:grid-cols-3">
                                <dt className="text-muted-foreground">
                                    Pickup
                                </dt>
                                <dd className="text-white/85 sm:col-span-2">
                                    {formatBookingTime(booking.pickup_at)},{' '}
                                    {booking.pickup_mode === 'office'
                                        ? 'at our office'
                                        : booking.delivery_address}
                                </dd>
                            </div>
                            <div className="grid gap-0.5 sm:grid-cols-3">
                                <dt className="text-muted-foreground">
                                    Return
                                </dt>
                                <dd className="text-white/85 sm:col-span-2">
                                    {formatBookingTime(booking.return_at)},{' '}
                                    {booking.return_mode === 'office'
                                        ? 'at our office'
                                        : booking.return_address}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section>
                        <h2 className="text-lg font-semibold text-foreground">
                            If you cancel
                        </h2>
                        <div className="mt-3">
                            <CancellationTerms tiers={tiers} />
                        </div>
                    </section>
                </div>

                <aside className="min-w-0 lg:col-span-5">
                    {quote && <QuoteSummary quote={quote} />}
                </aside>
            </div>
        </>
    );
}
