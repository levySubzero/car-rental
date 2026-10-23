import { Head, router, useForm } from '@inertiajs/react';
import { Download, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Field, inputClass } from '@/components/public/form-field';
import { Button } from '@/components/ui/button';
import { formatBookingTime } from '@/lib/booking';
import { cn } from '@/lib/utils';
import { open, restart, voucher } from '@/routes/portal';
import { store as storeRequest } from '@/routes/portal/requests';
import type { Money } from '@/types';

type PortalBooking = {
    reference: string;
    car: string;
    pickup_at: string;
    return_at: string;
    status: string;
    status_label: string;
    total: Money;
    balance: Money;
    has_voucher: boolean;
    accepts_requests: boolean;
    requests: {
        id: number;
        type_label: string;
        status: 'open' | 'handled';
        status_label: string;
        created_at: string | null;
    }[];
};

/**
 * My bookings, signed in (M10B): each booking with its status, a link to its page (to pay,
 * or see the details), the voucher once reserved, and a cancel or change request.
 */
export default function MyBookingsList({
    identifier,
    bookings,
}: {
    identifier: string;
    bookings: PortalBooking[];
}) {
    return (
        <>
            <Head title="My bookings">
                <meta
                    head-key="robots"
                    name="robots"
                    content="noindex, nofollow"
                />
            </Head>

            <section className="tone-charcoal">
                <div className="mx-auto flex max-w-7xl flex-wrap items-end justify-between gap-4 px-4 py-10 sm:px-6 lg:px-8">
                    <div className="min-w-0">
                        <h1 className="font-display text-3xl text-foreground sm:text-4xl">
                            My bookings
                        </h1>
                        <p className="mt-2 truncate text-sm text-white/70">
                            Signed in as {identifier}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.post(restart().url)}
                    >
                        Sign out
                    </Button>
                </div>
            </section>

            <div className="mx-auto grid max-w-4xl gap-6 px-4 py-12 sm:px-6">
                {bookings.length === 0 ? (
                    <p className="text-white/80">No bookings found.</p>
                ) : (
                    bookings.map((booking) => (
                        <BookingCard
                            key={booking.reference}
                            booking={booking}
                        />
                    ))
                )}
            </div>
        </>
    );
}

function BookingCard({ booking }: { booking: PortalBooking }) {
    const [requesting, setRequesting] = useState(false);

    return (
        <article className="grid gap-4 rounded-lg border border-white/10 bg-surface p-5 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm text-white/60">{booking.reference}</p>
                    <h2 className="text-lg font-semibold text-foreground">
                        {booking.car}
                    </h2>
                    <p className="mt-1 text-sm text-white/80">
                        {formatBookingTime(booking.pickup_at)} to{' '}
                        {formatBookingTime(booking.return_at)}
                    </p>
                </div>
                <span className="rounded-full border border-gold/40 px-3 py-1 text-sm text-gold">
                    {booking.status_label}
                </span>
            </div>

            <dl className="grid grid-cols-2 gap-2 text-sm sm:max-w-sm">
                <dt className="text-white/60">Total</dt>
                <dd className="text-right text-foreground tabular-nums">
                    {booking.total.display}
                </dd>
                <dt className="text-white/60">Balance at handover</dt>
                <dd className="text-right text-foreground tabular-nums">
                    {booking.balance.display}
                </dd>
            </dl>

            {booking.requests.length > 0 && (
                <ul className="grid gap-1 text-sm text-white/70">
                    {booking.requests.map((request) => (
                        <li key={request.id}>
                            {request.type_label} request sent{' '}
                            {formatBookingTime(request.created_at)}:{' '}
                            <span
                                className={cn(
                                    request.status === 'open'
                                        ? 'text-gold'
                                        : 'text-foreground',
                                )}
                            >
                                {request.status === 'open'
                                    ? 'waiting for our team'
                                    : 'handled'}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            <div className="flex flex-wrap gap-2">
                <Button
                    onClick={() => router.post(open(booking.reference).url)}
                >
                    <ExternalLink /> Open booking
                </Button>
                {booking.has_voucher && (
                    <Button variant="outline" asChild>
                        <a href={voucher(booking.reference).url}>
                            <Download /> Voucher
                        </a>
                    </Button>
                )}
                {booking.accepts_requests && !requesting && (
                    <Button variant="ghost" onClick={() => setRequesting(true)}>
                        Change or cancel
                    </Button>
                )}
            </div>

            {requesting && (
                <RequestForm
                    reference={booking.reference}
                    onDone={() => setRequesting(false)}
                />
            )}
        </article>
    );
}

function RequestForm({
    reference,
    onDone,
}: {
    reference: string;
    onDone: () => void;
}) {
    const form = useForm<{ type: 'cancel' | 'change'; message: string }>({
        type: 'change',
        message: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeRequest(reference).url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            className="grid gap-4 border-t border-white/10 pt-4"
        >
            <fieldset className="grid gap-2">
                <legend className="mb-1 text-sm text-white/80">
                    What would you like?
                </legend>
                {(
                    [
                        ['change', 'Change the booking'],
                        ['cancel', 'Cancel the booking'],
                    ] as const
                ).map(([value, label]) => (
                    <label
                        key={value}
                        className="flex items-center gap-2 text-sm text-foreground"
                    >
                        <input
                            type="radio"
                            name={`type-${reference}`}
                            value={value}
                            checked={form.data.type === value}
                            onChange={() => form.setData('type', value)}
                            className="accent-gold"
                        />
                        {label}
                    </label>
                ))}
            </fieldset>
            <Field
                id={`message-${reference}`}
                label="Message"
                error={form.errors.message}
                hint={
                    form.data.type === 'cancel'
                        ? 'Refunds follow the cancellation terms on your booking. Our team will confirm the amount.'
                        : 'Tell us what to change, such as dates, times or the delivery address.'
                }
            >
                {(id, describedBy, invalid) => (
                    <textarea
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        rows={4}
                        required
                        value={form.data.message}
                        onChange={(event) =>
                            form.setData('message', event.target.value)
                        }
                        className={cn(inputClass, 'h-auto py-3')}
                    />
                )}
            </Field>
            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={form.processing}>
                    Send request
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    Never mind
                </Button>
            </div>
        </form>
    );
}
