import { Head } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { CancellationTerms } from '@/components/public/booking/cancellation-terms';
import { PaymentForm } from '@/components/public/booking/payment-form';
import { QuoteSummary } from '@/components/public/booking/quote-summary';
import { BrandIcon } from '@/components/public/icons';
import { useSite } from '@/hooks/use-site';
import { formatBookingTime } from '@/lib/booking';
import { whatsappLink } from '@/lib/whatsapp';
import type {
    Booking,
    BookingPayment,
    CancellationTier,
    MediaCollectionLimits,
    PublicPaymentMethod,
    PublicQuote,
} from '@/types';

/**
 * The renter's booking after submit (M8.16): its status, the frozen quote and its own
 * cancellation terms. While it waits for payment, the renter pays here (M9.2, M9.3).
 */
export default function BookingStatus({
    token,
    booking,
    quote,
    cancellation_tiers: tiers,
    payment_methods: methods,
    payment,
    proof_limits: proofLimits,
}: {
    token: string;
    booking: Booking;
    quote: PublicQuote | null;
    cancellation_tiers: CancellationTier[];
    payment_methods: PublicPaymentMethod[];
    payment: BookingPayment | null;
    proof_limits: MediaCollectionLimits;
}) {
    const { site } = useSite();
    const chat = whatsappLink(
        site.whatsapp_number,
        site.whatsapp_templates.booking,
        { reference: booking.reference },
    );
    const canPay =
        booking.status === 'awaiting_payment' ||
        booking.status === 'payment_rejected';
    const until = booking.expires_at
        ? ` until ${formatBookingTime(booking.expires_at)}`
        : '';

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
                    {canPay && (
                        <section className="rounded-lg border border-gold/40 bg-surface p-6">
                            <h2 className="text-lg font-semibold text-foreground">
                                {booking.status === 'payment_rejected'
                                    ? 'Please pay again'
                                    : 'Next: pay the reservation'}
                            </h2>
                            {booking.status === 'payment_rejected' &&
                                payment?.rejection_reason && (
                                    <p
                                        role="alert"
                                        className="mt-3 rounded-md border border-red-400/40 bg-red-400/10 px-3 py-2 text-sm text-red-200"
                                    >
                                        We could not match your last payment:{' '}
                                        {payment.rejection_reason}
                                    </p>
                                )}
                            <p className="mt-2 text-sm text-white/80">
                                Pay{' '}
                                {quote && (
                                    <strong className="text-foreground">
                                        {quote.reservation.display}
                                    </strong>
                                )}{' '}
                                now to reserve the car; the rest is due at
                                handover. We are holding the car for you
                                {until}.
                            </p>
                            <div className="mt-6">
                                <PaymentForm
                                    token={token}
                                    reference={booking.reference}
                                    methods={methods}
                                    proofLimits={proofLimits}
                                />
                            </div>
                        </section>
                    )}

                    {booking.status === 'payment_submitted' && (
                        <Notice title="We are checking your payment">
                            Thank you. Our team is matching your{' '}
                            {payment?.method_name ?? ''} payment and will
                            confirm your booking shortly. The car stays held for
                            you meanwhile.
                        </Notice>
                    )}

                    {booking.status === 'awaiting_cash' && (
                        <Notice title="Pay cash at our office">
                            Bring{' '}
                            {quote && (
                                <strong className="text-foreground">
                                    {quote.reservation.display}
                                </strong>
                            )}{' '}
                            to our office
                            {site.address ? ` (${site.address})` : ''} and quote{' '}
                            {booking.reference}. We hold the car
                            {until}.
                        </Notice>
                    )}

                    {booking.status === 'reserved' &&
                        booking.reservation_paid && (
                            <Notice title="Your booking is confirmed">
                                We received{' '}
                                <strong className="text-foreground">
                                    {booking.reservation_paid.display}
                                </strong>
                                .
                                {booking.balance_due &&
                                    ` ${booking.balance_due.display} is due at handover, with the security deposit.`}
                            </Notice>
                        )}

                    {(canPay ||
                        booking.status === 'payment_submitted' ||
                        booking.status === 'awaiting_cash') &&
                        chat && (
                            <p className="-mt-4 text-sm text-muted-foreground">
                                Questions?{' '}
                                <a
                                    href={chat}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-gold hover:underline"
                                >
                                    <BrandIcon
                                        name="whatsapp"
                                        className="size-3.5"
                                    />
                                    Chat with us on WhatsApp
                                </a>
                            </p>
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

function Notice({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-lg border border-gold/40 bg-surface p-6">
            <h2 className="text-lg font-semibold text-foreground">{title}</h2>
            <p className="mt-2 text-sm text-white/80">{children}</p>
        </section>
    );
}
