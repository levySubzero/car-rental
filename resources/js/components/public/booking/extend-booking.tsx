import { router, useForm, useHttp } from '@inertiajs/react';
import { AlertTriangle, CalendarPlus } from 'lucide-react';
import { useEffect, useState } from 'react';
import BookingExtensionController from '@/actions/App/Http/Controllers/Public/BookingExtensionController';
import { Field, inputClass } from '@/components/public/form-field';
import { Button } from '@/components/ui/button';
import { formatBookingTime, fromLocalInput, toLocalInput } from '@/lib/booking';
import { cn } from '@/lib/utils';
import type { Booking, BookingExtension, Money } from '@/types';

type ExtensionPreview = {
    quote: {
        from: string;
        to: string;
        extra_days: number;
        chain_days: number;
        amount: Money;
        lines: { code: string; label: string; amount: Money | null }[];
    };
    available: boolean;
};

/**
 * Keeping the car longer (D50): the extensions asked for so far, and a form for another with a
 * live price. An extension is approved by our team, then paid in full on its own page.
 */
export function ExtendBooking({
    token,
    booking,
}: {
    token: string;
    booking: Booking;
}) {
    const extensions = booking.extensions ?? [];

    if (!booking.can_extend && extensions.length === 0) {
        return null;
    }

    return (
        <section className="rounded-lg bg-surface p-6">
            <h2 className="flex items-center gap-2 text-lg font-semibold text-foreground">
                <CalendarPlus aria-hidden className="size-5 text-gold" />
                Keep the car longer
            </h2>
            <p className="mt-2 text-sm text-white/80">
                The car is due back{' '}
                <strong className="text-foreground">
                    {formatBookingTime(booking.chain_end)}
                </strong>
                . Extra days are priced at today's rates and paid in full once
                our team approves them.
            </p>

            {extensions.length > 0 && (
                <ul className="mt-4 divide-y divide-white/10 rounded-md border border-white/10 text-sm">
                    {extensions.map((extension) => (
                        <ExtensionRow
                            key={extension.reference}
                            token={token}
                            extension={extension}
                        />
                    ))}
                </ul>
            )}

            {booking.can_extend && (
                <ExtendForm token={token} chainEnd={booking.chain_end} />
            )}
        </section>
    );
}

function ExtensionRow({
    token,
    extension,
}: {
    token: string;
    extension: BookingExtension;
}) {
    const [opening, setOpening] = useState(false);

    return (
        <li className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div className="grid gap-0.5">
                <span className="font-medium text-foreground">
                    {extension.reference} · {extension.status_label}
                </span>
                <span className="text-muted-foreground">
                    To {formatBookingTime(extension.to)} · {extension.days}{' '}
                    {extension.days === 1 ? 'day' : 'days'} ·{' '}
                    {extension.total.display}
                </span>
            </div>
            {extension.awaiting_payment && (
                <Button
                    size="sm"
                    disabled={opening}
                    onClick={() => {
                        setOpening(true);
                        router.post(
                            BookingExtensionController.open.url({
                                token,
                                reference: extension.reference,
                            }),
                            {},
                            { onFinish: () => setOpening(false) },
                        );
                    }}
                >
                    Pay now
                </Button>
            )}
        </li>
    );
}

function ExtendForm({ token, chainEnd }: { token: string; chainEnd: string }) {
    const form = useForm({ return_at: '', note: '' });
    const { preview, error, loading } = useExtensionPrice(
        token,
        fromLocalInput(form.data.return_at),
    );

    return (
        <form
            className="mt-5 grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    return_at: fromLocalInput(data.return_at),
                    note: data.note || null,
                }));
                form.post(BookingExtensionController.store.url(token), {
                    preserveScroll: true,
                    onSuccess: () => form.reset(),
                });
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="New return date and time"
                    error={form.errors.return_at}
                >
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            type="datetime-local"
                            required
                            min={toLocalInput(chainEnd)}
                            className={inputClass}
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            value={form.data.return_at}
                            onChange={(e) =>
                                form.setData('return_at', e.target.value)
                            }
                        />
                    )}
                </Field>
                <Field
                    label="Note for our team (optional)"
                    error={form.errors.note}
                >
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            maxLength={500}
                            className={inputClass}
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            value={form.data.note}
                            onChange={(e) =>
                                form.setData('note', e.target.value)
                            }
                        />
                    )}
                </Field>
            </div>

            {form.data.return_at !== '' && (
                <div
                    aria-live="polite"
                    className={cn(
                        'rounded-md border border-white/10 p-4 text-sm',
                        loading && 'opacity-60',
                    )}
                >
                    {error && <p className="text-red-400">{error}</p>}
                    {!error && !preview && (
                        <p className="text-muted-foreground">
                            Working out the price…
                        </p>
                    )}
                    {!error && preview && (
                        <>
                            <p className="text-white/85">
                                {preview.quote.extra_days} extra{' '}
                                {preview.quote.extra_days === 1
                                    ? 'day'
                                    : 'days'}
                                , to {formatBookingTime(preview.quote.to)}
                            </p>
                            <p className="mt-1 text-lg font-semibold text-gold">
                                {preview.quote.amount.display}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Paid in full once approved. Paying online adds
                                the transaction cost shown at payment.
                            </p>
                            {!preview.available && (
                                <p
                                    role="alert"
                                    className="mt-3 flex items-start gap-2 text-red-400"
                                >
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                    The car is booked by someone else for some
                                    of these days. Please choose an earlier
                                    return, or chat with us.
                                </p>
                            )}
                        </>
                    )}
                </div>
            )}

            <div>
                <Button
                    type="submit"
                    disabled={form.processing || !preview || !preview.available}
                >
                    Ask for these days
                </Button>
            </div>
        </form>
    );
}

/**
 * The extension's price from the server (D31), half a second after the date stops changing.
 */
function useExtensionPrice(token: string, returnAt: string) {
    const http = useHttp<{ return_at: string }, ExtensionPreview>({
        return_at: returnAt,
    });
    const [preview, setPreview] = useState<ExtensionPreview | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setPreview(null);
        setError(null);

        if (!returnAt) {
            return;
        }

        const timer = window.setTimeout(async () => {
            http.transform(() => ({ return_at: returnAt }));

            try {
                const result = await http.post(
                    BookingExtensionController.preview.url(token),
                );

                setPreview(result ?? null);
                setError(
                    result
                        ? null
                        : (Object.values(http.errors)[0] ??
                              'These dates cannot be priced.'),
                );
            } catch {
                setError('The price could not be loaded. Try again shortly.');
            }
        }, 500);

        return () => window.clearTimeout(timer);
        // The date string stands for the request.
    }, [returnAt, token]);

    return { preview, error, loading: http.processing };
}
