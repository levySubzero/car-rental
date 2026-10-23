import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, Check } from 'lucide-react';
import { useState } from 'react';
import { CancellationTerms } from '@/components/public/booking/cancellation-terms';
import { DriverForm } from '@/components/public/booking/driver-form';
import { QuoteSummary } from '@/components/public/booking/quote-summary';
import { TripForm } from '@/components/public/booking/trip-form';
import { Button } from '@/components/ui/button';
import { formatBookingTime } from '@/lib/booking';
import { cn } from '@/lib/utils';
import { rentalPolicy } from '@/routes';
import { submit as submitBooking, trip } from '@/routes/bookings';
import type {
    Booking,
    CancellationTier,
    DocumentOptions,
    PublicQuote,
    TripOptions,
} from '@/types';

type Step = Booking['current_step'];

const STEPS: { key: Step; label: string }[] = [
    { key: 'trip', label: 'Trip' },
    { key: 'driver', label: 'Driver' },
    { key: 'review', label: 'Review' },
];

/**
 * A draft booking (M8.18): three steps saved on the server, so a refresh or a later visit
 * to the link opens where the renter left off. Steps already reached can be revisited.
 */
export default function BookingWizard({
    token,
    booking,
    options,
    documents,
    quote,
    quote_error: quoteError,
    warnings,
    cancellation_tiers: tiers,
}: {
    token: string;
    booking: Booking;
    options: TripOptions;
    documents: DocumentOptions;
    quote: PublicQuote | null;
    quote_error: string | null;
    warnings: string[];
    cancellation_tiers: CancellationTier[];
}) {
    const reached = STEPS.findIndex(
        (step) => step.key === booking.current_step,
    );
    const [step, setStep] = useState<Step>(booking.current_step);
    // The server moves the step on after a save; follow it.
    const [seen, setSeen] = useState<Step>(booking.current_step);

    if (seen !== booking.current_step) {
        setSeen(booking.current_step);
        setStep(booking.current_step);
    }

    return (
        <>
            <Head title={`Book the ${booking.car.name}`}>
                <meta
                    head-key="robots"
                    name="robots"
                    content="noindex, nofollow"
                />
            </Head>

            <section className="tone-charcoal">
                <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                    <h1 className="font-display text-3xl text-foreground sm:text-4xl">
                        {`Book the ${booking.car.name}`}
                    </h1>
                    <p className="mt-2 text-white/75">
                        {formatBookingTime(booking.pickup_at)} to{' '}
                        {formatBookingTime(booking.return_at)}
                    </p>
                    <p className="mt-4 max-w-2xl text-sm text-muted-foreground">
                        Your progress is saved. Keep this page’s link to come
                        back to it; it is private to you.
                    </p>
                    <ol className="mt-8 flex gap-2" aria-label="Booking steps">
                        {STEPS.map((item, index) => {
                            const done = index < reached;
                            const current = item.key === step;

                            return (
                                <li key={item.key} className="flex-1">
                                    <button
                                        type="button"
                                        disabled={index > reached}
                                        onClick={() => setStep(item.key)}
                                        aria-current={
                                            current ? 'step' : undefined
                                        }
                                        className={cn(
                                            'flex w-full items-center gap-2 border-t-2 pt-3 text-left text-sm disabled:cursor-not-allowed',
                                            current
                                                ? 'border-gold text-foreground'
                                                : done
                                                  ? 'border-gold/50 text-white/80 hover:text-foreground'
                                                  : 'border-white/15 text-muted-foreground',
                                        )}
                                    >
                                        <span
                                            aria-hidden
                                            className={cn(
                                                'grid size-6 shrink-0 place-items-center rounded-full border text-xs',
                                                current || done
                                                    ? 'border-gold text-gold'
                                                    : 'border-white/25',
                                            )}
                                        >
                                            {done ? (
                                                <Check className="size-3.5" />
                                            ) : (
                                                index + 1
                                            )}
                                        </span>
                                        {item.label}
                                    </button>
                                </li>
                            );
                        })}
                    </ol>
                </div>
            </section>

            <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                {step === 'trip' && (
                    <TripForm
                        carSlug={booking.car.slug}
                        options={options}
                        booking={booking}
                        action={{ url: trip(token).url, method: 'put' }}
                        submitLabel="Save and continue"
                    />
                )}
                {step === 'driver' && (
                    <DriverForm
                        token={token}
                        booking={booking}
                        documents={documents}
                    />
                )}
                {step === 'review' && (
                    <Review
                        token={token}
                        booking={booking}
                        quote={quote}
                        quoteError={quoteError}
                        warnings={warnings}
                        tiers={tiers}
                        onEdit={setStep}
                    />
                )}
            </div>
        </>
    );
}

function Review({
    token,
    booking,
    quote,
    quoteError,
    warnings,
    tiers,
    onEdit,
}: {
    token: string;
    booking: Booking;
    quote: PublicQuote | null;
    quoteError: string | null;
    warnings: string[];
    tiers: CancellationTier[];
    onEdit: (step: Step) => void;
}) {
    const form = useForm({ accept_terms: false });
    const errors = form.errors as Record<string, string | undefined>;
    const driver = booking.driver;
    const place = (mode: string, address: string | null) =>
        mode === 'office' ? 'At our office' : (address ?? 'An address');

    return (
        <div className="grid gap-10 lg:grid-cols-12">
            <div className="grid min-w-0 content-start gap-8 lg:col-span-7">
                {warnings.map((warning) => (
                    <p
                        key={warning}
                        role="status"
                        className="flex gap-3 rounded-md border border-gold/40 bg-gold/10 p-4 text-sm text-white/85"
                    >
                        <AlertTriangle
                            aria-hidden
                            className="size-5 shrink-0 text-gold"
                        />
                        {warning}
                    </p>
                ))}

                <Summary title="Trip" onEdit={() => onEdit('trip')}>
                    <Item label="Pickup">
                        {formatBookingTime(booking.pickup_at)},{' '}
                        {place(booking.pickup_mode, booking.delivery_address)}
                    </Item>
                    <Item label="Return">
                        {formatBookingTime(booking.return_at)},{' '}
                        {place(booking.return_mode, booking.return_address)}
                    </Item>
                    {booking.delivery_zone && (
                        <Item label="Delivery area">
                            {booking.delivery_zone}
                        </Item>
                    )}
                    {booking.flight_number && (
                        <Item label="Flight">{booking.flight_number}</Item>
                    )}
                </Summary>

                {driver && (
                    <Summary title="Driver" onEdit={() => onEdit('driver')}>
                        <Item label="Name">
                            {[
                                driver.first_name,
                                driver.second_name,
                                driver.surname,
                            ]
                                .filter(Boolean)
                                .join(' ')}
                        </Item>
                        <Item label="Contact">
                            {driver.email} · {driver.phone}
                        </Item>
                        <Item label="Documents">Uploaded</Item>
                    </Summary>
                )}

                <section aria-labelledby="cancellation-title">
                    <h2
                        id="cancellation-title"
                        className="text-lg font-semibold text-foreground"
                    >
                        If you cancel
                    </h2>
                    <div className="mt-3">
                        <CancellationTerms tiers={tiers} />
                    </div>
                </section>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(submitBooking(token).url, {
                            preserveScroll: true,
                        });
                    }}
                    className="grid gap-4"
                >
                    <label className="flex gap-3 text-sm text-white/85">
                        <input
                            type="checkbox"
                            checked={form.data.accept_terms}
                            onChange={(event) =>
                                form.setData(
                                    'accept_terms',
                                    event.target.checked,
                                )
                            }
                            aria-invalid={
                                errors.accept_terms ? true : undefined
                            }
                            className="mt-0.5 size-4 accent-[var(--color-gold)]"
                        />
                        <span>
                            I have read and accept the{' '}
                            <Link
                                href={rentalPolicy().url}
                                target="_blank"
                                className="text-gold underline-offset-4 hover:underline"
                            >
                                rental terms
                            </Link>
                            .
                        </span>
                    </label>
                    {Object.entries(errors).map(([key, message]) => (
                        <p key={key} className="text-sm text-red-400">
                            {message}
                        </p>
                    ))}
                    <div>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={form.processing || !quote}
                            className="h-12 px-8 font-semibold"
                        >
                            {form.processing ? 'Submitting…' : 'Submit booking'}
                        </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        We hold the car for you while you pay the reservation.
                        Payment details follow on the next page.
                    </p>
                </form>
            </div>

            <aside className="min-w-0 lg:col-span-5">
                <div className="lg:sticky lg:top-24">
                    {quote ? (
                        <QuoteSummary quote={quote} />
                    ) : (
                        <p className="rounded-lg bg-surface p-6 text-sm text-red-400">
                            {quoteError ?? 'This booking can’t be priced.'}
                        </p>
                    )}
                </div>
            </aside>
        </div>
    );
}

function Summary({
    title,
    onEdit,
    children,
}: {
    title: string;
    onEdit: () => void;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-lg bg-surface p-6">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-foreground">
                    {title}
                </h2>
                <button
                    type="button"
                    onClick={onEdit}
                    className="text-sm font-semibold text-gold underline-offset-4 hover:underline"
                >
                    Change
                </button>
            </div>
            <dl className="mt-4 grid gap-3 text-sm">{children}</dl>
        </section>
    );
}

function Item({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-0.5 sm:grid-cols-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-white/85 sm:col-span-2">{children}</dd>
        </div>
    );
}
