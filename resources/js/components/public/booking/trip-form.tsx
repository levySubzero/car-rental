import { useForm, useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { QuoteSummary } from '@/components/public/booking/quote-summary';
import { Field, inputClass } from '@/components/public/form-field';
import { Turnstile } from '@/components/public/turnstile';
import { Button } from '@/components/ui/button';
import { fromLocalInput, toLocalInput } from '@/lib/booking';
import { cn } from '@/lib/utils';
import { preview } from '@/routes/quote';
import type { Booking, HandoverMode, PublicQuote, TripOptions } from '@/types';

type Leg = 'delivery' | 'return';

const LEGS: { leg: Leg; mode: 'pickup_mode' | 'return_mode'; title: string }[] =
    [
        { leg: 'delivery', mode: 'pickup_mode', title: 'Pickup' },
        { leg: 'return', mode: 'return_mode', title: 'Return' },
    ];

/**
 * Step 1, trip and extras (M8.10, M8.21), with a live price from the server (D31). Starts
 * the draft on the booking page (with Turnstile) and edits it in the wizard.
 */
export function TripForm({
    carSlug,
    options,
    booking,
    action,
    turnstileSiteKey,
    submitLabel,
}: {
    carSlug: string;
    options: TripOptions;
    booking?: Booking;
    action: { url: string; method: 'post' | 'put' };
    // Set on the booking page only: starting a draft needs a challenge.
    turnstileSiteKey?: string | null;
    submitLabel: string;
}) {
    const form = useForm({
        pickup_at: toLocalInput(booking?.pickup_at),
        return_at: toLocalInput(booking?.return_at),
        pickup_mode: (booking?.pickup_mode ?? 'office') as HandoverMode,
        return_mode: (booking?.return_mode ?? 'office') as HandoverMode,
        delivery_zone_id: booking?.delivery_zone_id
            ? String(booking.delivery_zone_id)
            : '',
        delivery_address: booking?.delivery_address ?? '',
        delivery_landmark: booking?.delivery_landmark ?? '',
        delivery_lat: booking?.delivery_lat ?? '',
        delivery_lng: booking?.delivery_lng ?? '',
        delivery_contact_phone: booking?.delivery_contact_phone ?? '',
        return_address: booking?.return_address ?? '',
        return_landmark: booking?.return_landmark ?? '',
        return_lat: booking?.return_lat ?? '',
        return_lng: booking?.return_lng ?? '',
        return_contact_phone: booking?.return_contact_phone ?? '',
        extras: booking?.extras ?? ([] as string[]),
        flight_number: booking?.flight_number ?? '',
        'cf-turnstile-response': '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const usesAddress =
        form.data.pickup_mode === 'address' ||
        form.data.return_mode === 'address';
    const zone = options.zones.find(
        (candidate) => String(candidate.id) === form.data.delivery_zone_id,
    );

    const quote = useLivePrice(carSlug, {
        pickup_at: fromLocalInput(form.data.pickup_at),
        return_at: fromLocalInput(form.data.return_at),
        extras: form.data.extras,
        delivery_zone_id:
            usesAddress && form.data.delivery_zone_id
                ? Number(form.data.delivery_zone_id)
                : null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            pickup_at: fromLocalInput(data.pickup_at),
            return_at: fromLocalInput(data.return_at),
            delivery_zone_id: data.delivery_zone_id
                ? Number(data.delivery_zone_id)
                : null,
        }));
        form.submit(action.method, action.url, { preserveScroll: true });
    };

    const text = (key: keyof typeof form.data) => ({
        value: form.data[key] as string,
        onChange: (
            event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
        ) => form.setData(key, event.target.value as never),
    });

    return (
        <form
            onSubmit={submit}
            noValidate
            className="grid gap-10 lg:grid-cols-12"
        >
            <div className="grid min-w-0 gap-8 lg:col-span-7">
                <fieldset className="grid gap-5 sm:grid-cols-2">
                    <legend className="mb-4 text-lg font-semibold text-foreground">
                        When
                    </legend>
                    <Field
                        label="Pickup"
                        id="trip-pickup-at"
                        error={errors.pickup_at}
                        hint="Nairobi time"
                    >
                        {(id, describedBy, invalid) => (
                            <input
                                id={id}
                                type="datetime-local"
                                required
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                className={inputClass}
                                {...text('pickup_at')}
                            />
                        )}
                    </Field>
                    <Field
                        label="Return"
                        id="trip-return-at"
                        error={errors.return_at}
                        hint="Nairobi time"
                    >
                        {(id, describedBy, invalid) => (
                            <input
                                id={id}
                                type="datetime-local"
                                required
                                min={form.data.pickup_at || undefined}
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                className={inputClass}
                                {...text('return_at')}
                            />
                        )}
                    </Field>
                </fieldset>

                {LEGS.map(({ leg, mode, title }) => (
                    <fieldset key={leg} className="grid gap-4">
                        <legend className="mb-3 text-lg font-semibold text-foreground">
                            {title}
                        </legend>
                        <div
                            role="radiogroup"
                            aria-label={`${title} place`}
                            className="grid gap-3 sm:grid-cols-2"
                        >
                            {(['office', 'address'] as const).map((value) => (
                                <label
                                    key={value}
                                    className={cn(
                                        'flex cursor-pointer gap-3 rounded-md border p-4 text-sm',
                                        form.data[mode] === value
                                            ? 'border-gold bg-gold/10'
                                            : 'border-white/15',
                                    )}
                                >
                                    <input
                                        type="radio"
                                        name={mode}
                                        value={value}
                                        checked={form.data[mode] === value}
                                        onChange={() =>
                                            form.setData(mode, value)
                                        }
                                        className="mt-0.5 accent-[var(--color-gold)]"
                                    />
                                    <span>
                                        <span className="block font-medium text-foreground">
                                            {value === 'office'
                                                ? 'At our office'
                                                : leg === 'delivery'
                                                  ? 'Deliver to me'
                                                  : 'Collect from me'}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {value === 'office'
                                                ? (options.office.address ??
                                                  'Nairobi')
                                                : 'Anywhere in a delivery area'}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        {form.data[mode] === 'address' && (
                            <AddressFields
                                leg={leg}
                                errors={errors}
                                field={(key) =>
                                    text(key as keyof typeof form.data)
                                }
                            />
                        )}
                    </fieldset>
                ))}

                {usesAddress && (
                    <Field
                        label="Delivery area"
                        id="trip-zone"
                        error={errors.delivery_zone_id}
                        hint={
                            zone
                                ? [
                                      zone.description,
                                      zone.fee
                                          ? `${zone.fee.display} each way`
                                          : 'Fee quoted by our team',
                                  ]
                                      .filter(Boolean)
                                      .join(' · ')
                                : 'We check the address against the area before handover.'
                        }
                    >
                        {(id, describedBy, invalid) => (
                            <select
                                id={id}
                                aria-describedby={describedBy}
                                aria-invalid={invalid}
                                className={inputClass}
                                {...text('delivery_zone_id')}
                            >
                                <option value="">Choose an area</option>
                                {options.zones.map((candidate) => (
                                    <option
                                        key={candidate.id}
                                        value={candidate.id}
                                    >
                                        {candidate.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    </Field>
                )}

                {options.extras.length > 0 && (
                    <fieldset className="grid gap-3">
                        <legend className="mb-3 text-lg font-semibold text-foreground">
                            Extras
                        </legend>
                        {options.extras.map((extra) => {
                            const checked = form.data.extras.includes(
                                extra.code,
                            );

                            return (
                                <label
                                    key={extra.code}
                                    className={cn(
                                        'flex cursor-pointer gap-3 rounded-md border p-4 text-sm',
                                        checked
                                            ? 'border-gold bg-gold/10'
                                            : 'border-white/15',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        checked={checked}
                                        onChange={() =>
                                            form.setData(
                                                'extras',
                                                checked
                                                    ? form.data.extras.filter(
                                                          (code) =>
                                                              code !==
                                                              extra.code,
                                                      )
                                                    : [
                                                          ...form.data.extras,
                                                          extra.code,
                                                      ],
                                            )
                                        }
                                        className="mt-0.5 accent-[var(--color-gold)]"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="flex justify-between gap-4 font-medium text-foreground">
                                            {extra.name}
                                            <span className="tabular-nums">
                                                {extra.amount
                                                    ? `${extra.amount.display} ${extra.basis_label.toLowerCase()}`
                                                    : 'On request'}
                                            </span>
                                        </span>
                                        {extra.description && (
                                            <span className="text-muted-foreground">
                                                {extra.description}
                                            </span>
                                        )}
                                    </span>
                                </label>
                            );
                        })}
                        {errors.extras && (
                            <p className="text-sm text-red-400">
                                {errors.extras}
                            </p>
                        )}
                    </fieldset>
                )}

                <Field
                    label="Flight number (optional)"
                    id="trip-flight"
                    error={errors.flight_number}
                    hint="If you are landing in Nairobi, so we can meet your flight."
                    className="max-w-sm"
                >
                    {(id, describedBy, invalid) => (
                        <input
                            id={id}
                            aria-describedby={describedBy}
                            aria-invalid={invalid}
                            placeholder="KQ 101"
                            className={inputClass}
                            {...text('flight_number')}
                        />
                    )}
                </Field>

                {turnstileSiteKey !== undefined && (
                    <div className="grid gap-2">
                        <Turnstile
                            siteKey={turnstileSiteKey}
                            onToken={(token) =>
                                form.setData('cf-turnstile-response', token)
                            }
                        />
                        {errors['cf-turnstile-response'] && (
                            <p className="text-sm text-red-400">
                                {errors['cf-turnstile-response']}
                            </p>
                        )}
                    </div>
                )}

                <div>
                    <Button
                        type="submit"
                        size="lg"
                        disabled={form.processing}
                        className="h-12 px-8 font-semibold"
                    >
                        {form.processing ? 'Saving…' : submitLabel}
                    </Button>
                </div>
            </div>

            <aside className="min-w-0 lg:col-span-5" aria-live="polite">
                <div className="lg:sticky lg:top-24">
                    {quote.quote ? (
                        <QuoteSummary quote={quote.quote} />
                    ) : (
                        <div className="rounded-lg bg-surface p-6 text-sm text-muted-foreground">
                            {quote.error ??
                                (quote.loading
                                    ? 'Working out the price…'
                                    : 'Choose your dates to see the price.')}
                        </div>
                    )}
                </div>
            </aside>
        </form>
    );
}

function AddressFields({
    leg,
    errors,
    field: bind,
}: {
    leg: Leg;
    errors: Record<string, string | undefined>;
    field: (key: string) => {
        value: string;
        onChange: (
            event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>,
        ) => void;
    };
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                label="Address"
                id={`${leg}-address`}
                error={errors[`${leg}_address`]}
                hint="Estate, road and house or building"
                className="sm:col-span-2"
            >
                {(id, describedBy, invalid) => (
                    <input
                        id={id}
                        required
                        autoComplete="street-address"
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        className={inputClass}
                        {...bind(`${leg}_address`)}
                    />
                )}
            </Field>
            <Field
                label="Landmark (optional)"
                id={`${leg}-landmark`}
                error={errors[`${leg}_landmark`]}
                hint="Something the driver can find, such as a gate or shop"
            >
                {(id, describedBy, invalid) => (
                    <input
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        className={inputClass}
                        {...bind(`${leg}_landmark`)}
                    />
                )}
            </Field>
            <Field
                label="Phone for the driver"
                id={`${leg}-phone`}
                error={errors[`${leg}_contact_phone`]}
            >
                {(id, describedBy, invalid) => (
                    <input
                        id={id}
                        type="tel"
                        required
                        autoComplete="tel"
                        placeholder="+254 7…"
                        aria-describedby={describedBy}
                        aria-invalid={invalid}
                        className={inputClass}
                        {...bind(`${leg}_contact_phone`)}
                    />
                )}
            </Field>
        </div>
    );
}

type PreviewInput = {
    pickup_at: string;
    return_at: string;
    extras: string[];
    delivery_zone_id: number | null;
};

/**
 * Re-prices half a second after the trip stops changing (`quote-preview` allows 30 a minute).
 */
function useLivePrice(car: string, input: PreviewInput) {
    const http = useHttp<PreviewInput & { car: string }, PublicQuote>({
        car,
        ...input,
    });
    const [quote, setQuote] = useState<PublicQuote | null>(null);
    const [error, setError] = useState<string | null>(null);
    const key = JSON.stringify(input);

    useEffect(() => {
        if (!input.pickup_at || !input.return_at) {
            setQuote(null);
            setError(null);

            return;
        }

        const timer = window.setTimeout(async () => {
            http.transform(() => ({ car, ...input }));

            try {
                const result = await http.post(preview().url);

                setQuote(result ?? null);
                setError(
                    result
                        ? null
                        : (Object.values(http.errors)[0] ??
                              'These dates can’t be priced.'),
                );
            } catch {
                setQuote(null);
                setError('The price could not be loaded. Try again shortly.');
            }
        }, 500);

        return () => window.clearTimeout(timer);
        // `key` stands for the input, compared by value.
    }, [key, car]);

    return { quote, error, loading: http.processing };
}
