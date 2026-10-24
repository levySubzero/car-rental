import { router, useForm } from '@inertiajs/react';
import {
    Ban,
    CarFront,
    CircleCheck,
    KeyRound,
    Mail,
    MapPin,
    ReceiptText,
    UserX,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import BookingCancellationController from '@/actions/App/Http/Controllers/Admin/BookingCancellationController';
import BookingDeliveryController from '@/actions/App/Http/Controllers/Admin/BookingDeliveryController';
import BookingHandoverController from '@/actions/App/Http/Controllers/Admin/BookingHandoverController';
import BookingVoucherController from '@/actions/App/Http/Controllers/Admin/BookingVoucherController';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import {
    MoneyField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/admin/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { percentFromBp } from '@/lib/format';
import type { AdminBooking, Option, RefundSuggestion } from '@/types';

/**
 * A dialog that owns its trigger and its open state, and closes when its form succeeds.
 */
function ActionDialog({
    trigger,
    title,
    description,
    submitLabel,
    processing,
    onSubmit,
    children,
    destructive = false,
}: {
    trigger: ReactNode;
    title: string;
    description: ReactNode;
    submitLabel: string;
    processing: boolean;
    onSubmit: (close: () => void) => void;
    children: ReactNode;
    destructive?: boolean;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSubmit(() => setOpen(false));
                    }}
                    className="grid gap-4"
                >
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {children}
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={processing}
                        >
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Hand the car over (M11.6): the balance and deposit taken, odometer and fuel.
 */
export function HandoverDialog({
    booking,
    fuelLevels,
}: {
    booking: AdminBooking;
    fuelLevels: Option[];
}) {
    const form = useForm({
        odometer_km: '',
        fuel_level: 'full',
        balance_received: booking.balance.major,
        deposit_received: booking.security_deposit?.major ?? '0',
        notes: '',
    });
    const differs =
        form.data.balance_received !== '' &&
        Number(form.data.balance_received) !== Number(booking.balance.major);

    return (
        <ActionDialog
            trigger={
                <Button size="sm">
                    <KeyRound /> Hand over
                </Button>
            }
            title={`Hand over ${booking.reference}`}
            description="Record what was taken and the car's readings. The booking goes on hire."
            submitLabel="Record handover"
            processing={form.processing}
            onSubmit={(close) =>
                form.post(
                    BookingHandoverController.handover.url(booking.reference),
                    { preserveScroll: true, onSuccess: close },
                )
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    name="odometer_km"
                    label="Odometer (km)"
                    required
                    inputMode="numeric"
                    value={form.data.odometer_km}
                    error={form.errors.odometer_km}
                    onChange={(e) =>
                        form.setData('odometer_km', e.target.value)
                    }
                />
                <SelectField
                    name="fuel_level"
                    label="Fuel"
                    options={fuelLevels}
                    value={form.data.fuel_level}
                    error={form.errors.fuel_level}
                    onChange={(e) => form.setData('fuel_level', e.target.value)}
                />
            </div>
            <MoneyField
                name="balance_received"
                label="Balance received"
                required
                value={form.data.balance_received}
                error={form.errors.balance_received}
                description={`Due: ${booking.balance.display}`}
                onChange={(e) =>
                    form.setData('balance_received', e.target.value)
                }
            />
            <MoneyField
                name="deposit_received"
                label="Security deposit received"
                required
                value={form.data.deposit_received}
                error={form.errors.deposit_received}
                description={
                    booking.security_deposit
                        ? `Quoted: ${booking.security_deposit.display}`
                        : undefined
                }
                onChange={(e) =>
                    form.setData('deposit_received', e.target.value)
                }
            />
            <TextareaField
                name="notes"
                label={differs ? 'Why does the balance differ?' : 'Notes'}
                required={differs}
                rows={3}
                value={form.data.notes}
                error={form.errors.notes}
                description="Damage already on the car, the deposit method, anything agreed."
                onChange={(e) => form.setData('notes', e.target.value)}
            />
        </ActionDialog>
    );
}

/**
 * Take the car back (M11.7): readings, the excess over the allowance, and the deposit.
 */
export function ReturnDialog({
    booking,
    fuelLevels,
}: {
    booking: AdminBooking;
    fuelLevels: Option[];
}) {
    const deposit = booking.handover?.deposit_received;
    const form = useForm({
        odometer_km: '',
        fuel_level: 'full',
        deposit_refunded: deposit?.major ?? '0',
        extra_charges: '0',
        notes: '',
    });
    const start = booking.handover?.odometer_km ?? null;
    const perDay = booking.quote?.mileage_km_per_day ?? null;
    const allowance =
        perDay === null || !booking.quote ? null : perDay * booking.quote.days;
    const driven =
        start !== null && form.data.odometer_km !== ''
            ? Number(form.data.odometer_km) - start
            : null;
    const excess =
        driven === null || allowance === null
            ? 0
            : Math.max(0, driven - allowance);
    const kept =
        deposit !== null &&
        deposit !== undefined &&
        Number(form.data.deposit_refunded) < Number(deposit.major);

    return (
        <ActionDialog
            trigger={
                <Button size="sm">
                    <CarFront /> Record return
                </Button>
            }
            title={`Return ${booking.reference}`}
            description="Record the car's readings and settle the deposit. The booking is completed."
            submitLabel="Record return"
            processing={form.processing}
            onSubmit={(close) =>
                form.post(
                    BookingHandoverController.return.url(booking.reference),
                    { preserveScroll: true, onSuccess: close },
                )
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    name="odometer_km"
                    label="Odometer (km)"
                    required
                    inputMode="numeric"
                    value={form.data.odometer_km}
                    error={form.errors.odometer_km}
                    description={
                        start !== null ? `At handover: ${start} km` : undefined
                    }
                    onChange={(e) =>
                        form.setData('odometer_km', e.target.value)
                    }
                />
                <SelectField
                    name="fuel_level"
                    label="Fuel"
                    options={fuelLevels}
                    value={form.data.fuel_level}
                    error={form.errors.fuel_level}
                    onChange={(e) => form.setData('fuel_level', e.target.value)}
                />
            </div>
            <p className="rounded-md bg-muted px-3 py-2 text-sm">
                {driven === null
                    ? 'Enter the odometer to see the distance driven.'
                    : `${driven} km driven`}
                {' · '}
                {allowance === null
                    ? 'unlimited mileage'
                    : `${allowance} km allowed`}
                {excess > 0 && (
                    <strong className="block">
                        {excess} km over the allowance
                    </strong>
                )}
            </p>
            <MoneyField
                name="extra_charges"
                label="Extra charges"
                value={form.data.extra_charges}
                error={form.errors.extra_charges}
                description="Excess km, fuel, cleaning or damage, taken from the deposit or paid."
                onChange={(e) => form.setData('extra_charges', e.target.value)}
            />
            <MoneyField
                name="deposit_refunded"
                label="Deposit refunded"
                required
                value={form.data.deposit_refunded}
                error={form.errors.deposit_refunded}
                description={
                    deposit
                        ? `Taken at handover: ${deposit.display}`
                        : undefined
                }
                onChange={(e) =>
                    form.setData('deposit_refunded', e.target.value)
                }
            />
            <TextareaField
                name="notes"
                label={kept ? 'Why is part of the deposit kept?' : 'Notes'}
                required={kept}
                rows={3}
                value={form.data.notes}
                error={form.errors.notes}
                onChange={(e) => form.setData('notes', e.target.value)}
            />
        </ActionDialog>
    );
}

/**
 * Cancel or mark a no-show (M11.8, M11.14): the suggested refund from the booking's own
 * tiers, which staff may change with a reason.
 */
export function CloseBookingDialog({
    booking,
    suggestion,
    noShow = false,
}: {
    booking: AdminBooking;
    suggestion: RefundSuggestion;
    noShow?: boolean;
}) {
    const form = useForm({
        reason: '',
        refund: suggestion.amount.major,
        refund_override_reason: '',
    });
    const overridden =
        form.data.refund !== '' &&
        Number(form.data.refund) !== Number(suggestion.amount.major);
    const paid = booking.reservation_paid;
    const action = noShow
        ? BookingCancellationController.noShow
        : BookingCancellationController.cancel;

    return (
        <ActionDialog
            trigger={
                <Button size="sm" variant="outline">
                    {noShow ? <UserX /> : <Ban />}
                    {noShow ? 'No-show' : 'Cancel booking'}
                </Button>
            }
            title={
                noShow
                    ? `Mark ${booking.reference} as a no-show`
                    : `Cancel ${booking.reference}`
            }
            description="The car is freed straight away. Any refund is paid back by hand; record it once sent."
            submitLabel={noShow ? 'Mark no-show' : 'Cancel booking'}
            destructive
            processing={form.processing}
            onSubmit={(close) =>
                form.post(action.url(booking.reference), {
                    preserveScroll: true,
                    onSuccess: close,
                })
            }
        >
            <div className="rounded-md bg-muted px-3 py-2 text-sm">
                {noShow ? (
                    <p>A no-show gets no refund unless you decide otherwise.</p>
                ) : (
                    <p>
                        {suggestion.notice_hours} hours before pickup.{' '}
                        {suggestion.tier
                            ? `Tier met: ${suggestion.tier.label ?? `${suggestion.tier.hours_before_pickup} h or more`} (${percentFromBp(suggestion.percent_bp)} refund).`
                            : booking.cancellation_tiers.length > 0
                              ? 'Less notice than every tier: no refund.'
                              : 'This booking has no cancellation tiers: no refund.'}
                    </p>
                )}
                <p className="mt-1">
                    Reservation paid: {paid?.display ?? 'nothing'} · suggested
                    refund: <strong>{suggestion.amount.display}</strong>
                </p>
            </div>
            <TextareaField
                name="reason"
                label={noShow ? 'What happened?' : 'Reason for cancelling'}
                required
                rows={2}
                value={form.data.reason}
                error={form.errors.reason}
                onChange={(e) => form.setData('reason', e.target.value)}
            />
            <MoneyField
                name="refund"
                label="Refund"
                required
                value={form.data.refund}
                error={form.errors.refund}
                onChange={(e) => form.setData('refund', e.target.value)}
            />
            {overridden && (
                <TextareaField
                    name="refund_override_reason"
                    label="Why change the suggested refund?"
                    required
                    rows={2}
                    value={form.data.refund_override_reason}
                    error={form.errors.refund_override_reason}
                    onChange={(e) =>
                        form.setData('refund_override_reason', e.target.value)
                    }
                />
            )}
        </ActionDialog>
    );
}

/**
 * Record a refund paid back by hand (D34).
 */
export function MarkRefundedDialog({ booking }: { booking: AdminBooking }) {
    const form = useForm({ reference: '' });

    return (
        <ActionDialog
            trigger={
                <Button size="sm">
                    <ReceiptText /> Mark refunded
                </Button>
            }
            title={`Refund ${booking.cancellation?.refund.display}`}
            description="Enter the reference of the M-Pesa, bank or card refund you sent."
            submitLabel="Mark refunded"
            processing={form.processing}
            onSubmit={(close) =>
                form.post(
                    BookingCancellationController.refunded.url(
                        booking.reference,
                    ),
                    { preserveScroll: true, onSuccess: close },
                )
            }
        >
            <TextField
                name="reference"
                label="Refund reference"
                required
                autoComplete="off"
                value={form.data.reference}
                error={form.errors.reference}
                onChange={(e) => form.setData('reference', e.target.value)}
            />
        </ActionDialog>
    );
}

/**
 * Quote the delivery fee or move the booking to another zone (M11.13). Only the balance
 * changes, and the renter is emailed.
 */
export function DeliveryDialog({
    booking,
    zones,
}: {
    booking: AdminBooking;
    zones: (Option & { priced: boolean })[];
}) {
    const current = String(booking.delivery?.zone?.id ?? '');
    const form = useForm({
        delivery_zone_id: current,
        fee: booking.delivery?.fee?.major ?? '',
    });
    const zone = zones.find((z) => z.value === form.data.delivery_zone_id);
    const moving = form.data.delivery_zone_id !== current;
    const needsFee = !moving || !zone?.priced;

    return (
        <ActionDialog
            trigger={
                <Button size="sm" variant="outline">
                    <MapPin /> Delivery fee or zone
                </Button>
            }
            title="Delivery"
            description="The reservation already paid stays the same; the balance due at handover changes, and the renter is emailed."
            submitLabel="Save delivery"
            processing={form.processing}
            onSubmit={(close) => {
                form.transform((data) => ({
                    delivery_zone_id: moving ? data.delivery_zone_id : null,
                    fee: needsFee ? data.fee : null,
                }));
                form.put(
                    BookingDeliveryController.update.url(booking.reference),
                    {
                        preserveScroll: true,
                        onSuccess: close,
                    },
                );
            }}
        >
            <SelectField
                name="delivery_zone_id"
                label="Zone"
                options={zones}
                value={form.data.delivery_zone_id}
                error={form.errors.delivery_zone_id}
                onChange={(e) =>
                    form.setData('delivery_zone_id', e.target.value)
                }
            />
            {needsFee && (
                <MoneyField
                    name="fee"
                    label="Delivery fee"
                    required
                    value={form.data.fee}
                    error={form.errors.fee}
                    onChange={(e) => form.setData('fee', e.target.value)}
                />
            )}
        </ActionDialog>
    );
}

export function DeliveryCheckedButton({ booking }: { booking: AdminBooking }) {
    return (
        <Button
            size="sm"
            variant="outline"
            onClick={() =>
                router.post(
                    BookingDeliveryController.checked.url(booking.reference),
                    {},
                    { preserveScroll: true },
                )
            }
        >
            <CircleCheck /> Address is in this zone
        </Button>
    );
}

/**
 * Resend the confirmation, or replace the renter's link (M11.5).
 */
export function VoucherActions({ booking }: { booking: AdminBooking }) {
    const confirmed = ['reserved', 'active'].includes(booking.status);

    return (
        <>
            {confirmed && (
                <ConfirmDialog
                    trigger={
                        <Button size="sm" variant="outline">
                            <Mail /> Resend voucher
                        </Button>
                    }
                    title="Resend the voucher?"
                    description="The renter gets the voucher again with a new booking link; earlier links stop working."
                    confirmLabel="Resend"
                    onConfirm={() =>
                        new Promise((resolve) =>
                            router.post(
                                BookingVoucherController.resend.url(
                                    booking.reference,
                                ),
                                {},
                                { preserveScroll: true, onFinish: resolve },
                            ),
                        )
                    }
                />
            )}
            <ConfirmDialog
                trigger={
                    <Button size="sm" variant="outline">
                        <KeyRound /> New renter link
                    </Button>
                }
                title="Replace the renter's link?"
                description="Earlier links stop working. The new link is shown once, for you to send."
                confirmLabel="Replace link"
                onConfirm={() =>
                    new Promise((resolve) =>
                        router.post(
                            BookingVoucherController.rotate.url(
                                booking.reference,
                            ),
                            {},
                            { preserveScroll: true, onFinish: resolve },
                        ),
                    )
                }
            />
        </>
    );
}
