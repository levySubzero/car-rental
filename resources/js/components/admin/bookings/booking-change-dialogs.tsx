import { useForm, useHttp } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, CalendarClock, Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import BookingChangeController from '@/actions/App/Http/Controllers/Admin/BookingChangeController';
import {
    MoneyField,
    SelectField,
    TextField,
} from '@/components/admin/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatBookingTime, fromLocalInput, toLocalInput } from '@/lib/booking';
import { cn } from '@/lib/utils';
import type { AdminBooking, Money, Option } from '@/types';

type Figures = {
    car: string | null;
    pickup_at: string;
    return_at: string;
    days: number;
    total: Money;
    reservation: Money;
    balance: Money;
    deposit: Money | null;
};

type Preview = {
    before: Figures;
    after: Figures;
    difference: Money;
    available: boolean;
};

type Payload = Record<string, string | string[] | null>;

/**
 * Asks the server what the change would cost (the server is the only quote engine, D31),
 * half a second after the last keystroke.
 */
function usePreview<T = Preview>(
    url: string,
    payload: Payload,
    enabled: boolean,
) {
    const http = useHttp<Payload, T>(payload);
    const [preview, setPreview] = useState<T | null>(null);
    const [error, setError] = useState<string | null>(null);
    const key = JSON.stringify(payload);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const timer = window.setTimeout(async () => {
            http.transform(() => payload);

            try {
                const result = await http.post(url);

                setPreview(result ?? null);
                setError(
                    result
                        ? null
                        : (Object.values(http.errors)[0] ??
                              'This change cannot be priced.'),
                );
            } catch {
                setPreview(null);
                setError('The price could not be loaded. Try again shortly.');
            }
        }, 500);

        return () => window.clearTimeout(timer);
        // `key` stands for the payload, compared by value.
    }, [key, url, enabled]);

    return { preview, error, loading: http.processing };
}

function PreviewPanel({
    preview,
    error,
    loading,
    showCar = false,
}: {
    preview: Preview | null;
    error: string | null;
    loading: boolean;
    showCar?: boolean;
}) {
    if (error) {
        return (
            <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {error}
            </p>
        );
    }

    if (!preview) {
        return (
            <div className="flex items-center gap-2 rounded-md bg-muted px-3 py-3 text-sm text-muted-foreground">
                {loading && <Spinner />} Working out the new price…
            </div>
        );
    }

    const { before, after, difference } = preview;
    const rows: [string, ReactNode, ReactNode][] = [
        ...(showCar && before.car !== after.car
            ? ([['Car', before.car, after.car]] as [
                  string,
                  ReactNode,
                  ReactNode,
              ][])
            : []),
        ['Billable days', before.days, <strong key="d">{after.days}</strong>],
        [
            'Total',
            before.total.display,
            <strong key="t">{after.total.display}</strong>,
        ],
        [
            'Balance due',
            before.balance.display,
            <strong key="b">{after.balance.display}</strong>,
        ],
    ];

    if (before.deposit?.minor !== after.deposit?.minor) {
        rows.push([
            'Security deposit',
            before.deposit?.display ?? '—',
            after.deposit?.display ?? '—',
        ]);
    }

    return (
        <div
            className={cn(
                'grid gap-3 rounded-md border p-3 text-sm',
                loading && 'opacity-60',
            )}
        >
            <table className="w-full">
                <tbody>
                    {rows.map(([label, from, to]) => (
                        <tr key={label}>
                            <td className="py-1 text-muted-foreground">
                                {label}
                            </td>
                            <td className="py-1 text-right tabular-nums">
                                {from}
                            </td>
                            <td className="w-6 py-1 text-center text-muted-foreground">
                                <ArrowRight className="inline size-3.5" />
                            </td>
                            <td className="py-1 text-right tabular-nums">
                                {to}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {difference.minor !== 0 && (
                <p
                    className={cn(
                        'font-medium',
                        difference.minor > 0 ? 'text-gold' : 'text-emerald-500',
                    )}
                >
                    {difference.minor > 0
                        ? `+${difference.display} added to the balance`
                        : `${difference.display.replace('-', '')} less to pay`}
                </p>
            )}
            {!preview.available && (
                <p className="flex items-start gap-2 text-destructive">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    The car is booked for some of these dates. Pick other dates
                    or another car.
                </p>
            )}
        </div>
    );
}

function ChangeDialog({
    trigger,
    title,
    description,
    submitLabel,
    processing,
    disabled,
    onSubmit,
    onOpenChange,
    children,
}: {
    trigger: ReactNode;
    title: string;
    description: ReactNode;
    submitLabel: string;
    processing: boolean;
    disabled: boolean;
    onSubmit: (close: () => void) => void;
    onOpenChange: (open: boolean) => void;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const change = (value: boolean) => {
        setOpen(value);
        onOpenChange(value);
    };

    return (
        <Dialog open={open} onOpenChange={change}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSubmit(() => change(false));
                    }}
                    className="grid gap-4"
                >
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {children}
                    <DialogFooter>
                        <Button type="submit" disabled={processing || disabled}>
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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
 * Extend a confirmed or running hire (D50). The extra days become an extension booking of
 * their own, priced at the margin and paid in full: either the renter is sent a link to pay,
 * or staff record a payment they took and it is confirmed straight away.
 */
export function ExtendBookingDialog({
    booking,
    methods,
}: {
    booking: AdminBooking;
    methods: Option[];
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        return_at: '',
        total: '',
        payment: 'link' as 'link' | 'paid',
        paid: '',
        method: '',
        reference: '',
    });
    const payload = { return_at: fromLocalInput(form.data.return_at) };
    const { preview, error, loading } = usePreview<ExtensionPreview>(
        BookingChangeController.previewExtend.url(booking.reference),
        payload,
        open && form.data.return_at !== '',
    );
    const recorded = form.data.payment === 'paid';

    return (
        <ChangeDialog
            trigger={
                <Button size="sm" variant="outline">
                    <CalendarClock /> Extend
                </Button>
            }
            title={`Extend ${booking.reference}`}
            description={`Due back ${formatBookingTime(booking.chain_end)}. The extra days become an extension of this booking, priced at today's rates and paid in full.`}
            submitLabel={recorded ? 'Confirm extension' : 'Send link to pay'}
            processing={form.processing}
            disabled={!preview || !preview.available}
            onOpenChange={setOpen}
            onSubmit={(close) => {
                form.transform((data) => ({
                    return_at: fromLocalInput(data.return_at),
                    total: data.total || null,
                    payment: data.payment,
                    paid: data.payment === 'paid' ? data.paid || null : null,
                    method:
                        data.payment === 'paid' ? data.method || null : null,
                    reference:
                        data.payment === 'paid' ? data.reference || null : null,
                }));
                form.put(
                    BookingChangeController.extend.url(booking.reference),
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset();
                            close();
                        },
                    },
                );
            }}
        >
            <TextField
                name="return_at"
                label="New return date and time"
                type="datetime-local"
                required
                min={toLocalInput(booking.chain_end)}
                value={form.data.return_at}
                error={form.errors.return_at}
                onChange={(e) => form.setData('return_at', e.target.value)}
            />
            {form.data.return_at !== '' && (
                <ExtensionPreviewPanel
                    preview={preview}
                    error={error}
                    loading={loading}
                />
            )}
            <MoneyField
                name="total"
                label="Agreed price (optional)"
                description="Only to charge something other than the price above."
                value={form.data.total}
                error={form.errors.total}
                onChange={(e) => form.setData('total', e.target.value)}
            />
            <SelectField
                name="payment"
                label="Payment"
                options={[
                    {
                        value: 'link',
                        label: 'Email the renter a link to pay',
                    },
                    {
                        value: 'paid',
                        label: 'The renter has paid us: record it now',
                    },
                ]}
                value={form.data.payment}
                error={form.errors.payment}
                onChange={(e) =>
                    form.setData('payment', e.target.value as 'link' | 'paid')
                }
            />
            {recorded && (
                <div className="grid gap-3 sm:grid-cols-3">
                    <MoneyField
                        name="paid"
                        label="Amount taken"
                        placeholder={preview?.quote.amount.major ?? ''}
                        value={form.data.paid}
                        error={form.errors.paid}
                        onChange={(e) => form.setData('paid', e.target.value)}
                    />
                    <SelectField
                        name="method"
                        label="Method"
                        placeholder="Choose…"
                        options={methods}
                        value={form.data.method}
                        error={form.errors.method}
                        onChange={(e) => form.setData('method', e.target.value)}
                    />
                    <TextField
                        name="reference"
                        label="Reference"
                        value={form.data.reference}
                        error={form.errors.reference}
                        onChange={(e) =>
                            form.setData('reference', e.target.value)
                        }
                    />
                </div>
            )}
        </ChangeDialog>
    );
}

function ExtensionPreviewPanel({
    preview,
    error,
    loading,
}: {
    preview: ExtensionPreview | null;
    error: string | null;
    loading: boolean;
}) {
    if (error) {
        return (
            <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {error}
            </p>
        );
    }

    if (!preview) {
        return (
            <div className="flex items-center gap-2 rounded-md bg-muted px-3 py-3 text-sm text-muted-foreground">
                {loading && <Spinner />} Working out the price…
            </div>
        );
    }

    const { quote } = preview;

    return (
        <div
            className={cn(
                'grid gap-2 rounded-md border p-3 text-sm',
                loading && 'opacity-60',
            )}
        >
            <p className="text-muted-foreground">
                {formatBookingTime(quote.from)} → {formatBookingTime(quote.to)}{' '}
                · {quote.extra_days} extra{' '}
                {quote.extra_days === 1 ? 'day' : 'days'} ({quote.chain_days} in
                all)
            </p>
            <table className="w-full">
                <tbody>
                    {quote.lines.map((line) => (
                        <tr key={line.code}>
                            <td className="py-1 text-muted-foreground">
                                {line.label}
                            </td>
                            <td className="py-1 text-right tabular-nums">
                                {line.amount?.display ?? '—'}
                            </td>
                        </tr>
                    ))}
                    <tr className="border-t">
                        <td className="py-1 font-medium">To pay now</td>
                        <td className="py-1 text-right font-medium text-gold tabular-nums">
                            {quote.amount.display}
                        </td>
                    </tr>
                </tbody>
            </table>
            {!preview.available && (
                <p className="flex items-start gap-2 text-destructive">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    The car is booked for some of these dates. Pick an earlier
                    return.
                </p>
            )}
        </div>
    );
}

type Leg = 'delivery' | 'return';

/**
 * Edit a booking before handover: car, dates, handover and extras, priced again live.
 */
export function EditBookingDialog({
    booking,
    cars,
    zones,
    extras,
}: {
    booking: AdminBooking;
    cars: Option[];
    zones: Option[];
    extras: Option[];
}) {
    const [open, setOpen] = useState(false);
    const leg = (name: 'pickup' | 'return') => booking.delivery?.[name];
    const form = useForm({
        car_id: String(booking.car.id),
        pickup_at: toLocalInput(booking.pickup_at),
        return_at: toLocalInput(booking.return_at),
        pickup_mode: booking.pickup_mode as string,
        return_mode: booking.return_mode as string,
        delivery_zone_id: booking.delivery?.zone
            ? String(booking.delivery.zone.id)
            : '',
        delivery_address: leg('pickup')?.address ?? '',
        delivery_landmark: leg('pickup')?.landmark ?? '',
        delivery_lat: leg('pickup')?.lat ?? '',
        delivery_lng: leg('pickup')?.lng ?? '',
        delivery_contact_phone: leg('pickup')?.contact_phone ?? '',
        return_address: leg('return')?.address ?? '',
        return_landmark: leg('return')?.landmark ?? '',
        return_lat: leg('return')?.lat ?? '',
        return_lng: leg('return')?.lng ?? '',
        return_contact_phone: leg('return')?.contact_phone ?? '',
        extras: booking.extras,
        flight_number: booking.flight_number ?? '',
        total: '',
    });
    const usesZone =
        form.data.pickup_mode === 'address' ||
        form.data.return_mode === 'address';

    const payload = (data: typeof form.data) => ({
        ...data,
        pickup_at: fromLocalInput(data.pickup_at),
        return_at: fromLocalInput(data.return_at),
        delivery_zone_id: usesZone ? data.delivery_zone_id : null,
        total: data.total || null,
    });
    const { preview, error, loading } = usePreview(
        BookingChangeController.previewEdit.url(booking.reference),
        { ...payload(form.data), total: null },
        open && form.data.pickup_at !== '' && form.data.return_at !== '',
    );
    const modes: Option[] = [
        { value: 'office', label: 'At our office' },
        { value: 'address', label: 'At an address' },
    ];
    const text = (
        key: keyof typeof form.data,
        label: string,
        required = false,
    ) => (
        <TextField
            name={key}
            label={label}
            required={required}
            value={form.data[key] as string}
            error={form.errors[key]}
            onChange={(e) => form.setData(key, e.target.value)}
        />
    );
    const legFields = (name: Leg, title: string) => (
        <fieldset className="grid gap-3 rounded-md border p-3">
            <legend className="px-1 text-sm font-medium">{title}</legend>
            {text(`${name}_address`, 'Address', true)}
            <div className="grid gap-3 sm:grid-cols-2">
                {text(`${name}_landmark`, 'Landmark')}
                {text(`${name}_contact_phone`, 'Contact phone', true)}
            </div>
        </fieldset>
    );

    return (
        <ChangeDialog
            trigger={
                <Button size="sm" variant="outline">
                    <Pencil /> Edit booking
                </Button>
            }
            title={`Edit ${booking.reference}`}
            description="The trip is priced again at today's rates. Once paid, the reservation stays as it is and the difference goes on the balance."
            submitLabel="Save changes"
            processing={form.processing}
            disabled={
                !preview ||
                (!preview.available && booking.status !== 'requested')
            }
            onOpenChange={setOpen}
            onSubmit={(close) => {
                form.transform(payload);
                form.put(BookingChangeController.edit.url(booking.reference), {
                    preserveScroll: true,
                    onSuccess: close,
                });
            }}
        >
            <SelectField
                name="car_id"
                label="Car"
                options={cars}
                value={form.data.car_id}
                error={form.errors.car_id}
                onChange={(e) => form.setData('car_id', e.target.value)}
            />
            <div className="grid gap-3 sm:grid-cols-2">
                <TextField
                    name="pickup_at"
                    label="Pickup"
                    type="datetime-local"
                    required
                    value={form.data.pickup_at}
                    error={form.errors.pickup_at}
                    onChange={(e) => form.setData('pickup_at', e.target.value)}
                />
                <TextField
                    name="return_at"
                    label="Return"
                    type="datetime-local"
                    required
                    value={form.data.return_at}
                    error={form.errors.return_at}
                    onChange={(e) => form.setData('return_at', e.target.value)}
                />
                <SelectField
                    name="pickup_mode"
                    label="Pickup at"
                    options={modes}
                    value={form.data.pickup_mode}
                    error={form.errors.pickup_mode}
                    onChange={(e) =>
                        form.setData('pickup_mode', e.target.value)
                    }
                />
                <SelectField
                    name="return_mode"
                    label="Return at"
                    options={modes}
                    value={form.data.return_mode}
                    error={form.errors.return_mode}
                    onChange={(e) =>
                        form.setData('return_mode', e.target.value)
                    }
                />
            </div>
            {usesZone && (
                <SelectField
                    name="delivery_zone_id"
                    label="Delivery area"
                    placeholder="Choose an area"
                    options={zones}
                    value={form.data.delivery_zone_id}
                    error={form.errors.delivery_zone_id}
                    onChange={(e) =>
                        form.setData('delivery_zone_id', e.target.value)
                    }
                />
            )}
            {form.data.pickup_mode === 'address' &&
                legFields('delivery', 'Delivery')}
            {form.data.return_mode === 'address' &&
                legFields('return', 'Collection')}
            {extras.length > 0 && (
                <div className="grid gap-2">
                    <Label>Extras</Label>
                    {extras.map((extra) => (
                        <label
                            key={extra.value}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                checked={form.data.extras.includes(extra.value)}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'extras',
                                        checked === true
                                            ? [...form.data.extras, extra.value]
                                            : form.data.extras.filter(
                                                  (code) =>
                                                      code !== extra.value,
                                              ),
                                    )
                                }
                            />
                            {extra.label}
                        </label>
                    ))}
                </div>
            )}
            <PreviewPanel
                preview={preview}
                error={error}
                loading={loading}
                showCar
            />
            <MoneyField
                name="total"
                label="Agreed total (optional)"
                description="Only to charge something other than the new total above."
                value={form.data.total}
                error={form.errors.total}
                onChange={(e) => form.setData('total', e.target.value)}
            />
        </ChangeDialog>
    );
}
