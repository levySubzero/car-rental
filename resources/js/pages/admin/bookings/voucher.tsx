import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    Download,
    Info,
    Mail,
    Printer,
} from 'lucide-react';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import BookingVoucherController from '@/actions/App/Http/Controllers/Admin/BookingVoucherController';
import VoucherController from '@/actions/App/Http/Controllers/Admin/VoucherController';
import { BookingStatusBadge } from '@/components/admin/bookings/booking-status-badge';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { TextareaField, TextField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { formatBookingTime } from '@/lib/booking';
import type { BookingStatus, MediaItem } from '@/types';

type Fields = {
    renter: string;
    car: string;
    pickup_date: string;
    pickup_time: string;
    pickup_point: string;
    return_date: string;
    return_time: string;
    return_point: string;
    total: string;
    reservation_paid: string;
    balance: string;
    security_deposit: string;
};

const GROUPS: { title: string; fields: [keyof Fields, string][] }[] = [
    {
        title: 'Booking',
        fields: [
            ['renter', 'Client'],
            ['car', 'Car'],
        ],
    },
    {
        title: 'Pick-up',
        fields: [
            ['pickup_date', 'Date'],
            ['pickup_time', 'Time'],
            ['pickup_point', 'Place'],
        ],
    },
    {
        title: 'Return',
        fields: [
            ['return_date', 'Date'],
            ['return_time', 'Time'],
            ['return_point', 'Place'],
        ],
    },
    {
        title: 'Payment',
        fields: [
            ['total', 'Total rental'],
            ['reservation_paid', 'Reservation paid'],
            ['balance', 'Balance due at handover'],
            ['security_deposit', 'Security deposit'],
        ],
    },
];

/**
 * Review a voucher before it goes out, and send, download or print it at any point. Edits
 * reword this document only: the booking, the calendar and the ledger stay as they are.
 * Once the hire is over, or the booking is cancelled or a no-show, it is kept read-only.
 */
export default function BookingVoucher({
    booking,
    manageable,
    void: isVoid,
    voucher,
    fields,
    original,
    notes,
}: {
    booking: {
        reference: string;
        status: BookingStatus;
        status_label: string;
        email: string | null;
        phone: string | null;
        sent_at: string | null;
    };
    manageable: boolean;
    void: boolean;
    voucher: MediaItem | null;
    fields: Fields;
    original: Fields;
    notes: string;
}) {
    const can = useCan();
    const canEdit = can('bookings.resend-voucher') && manageable;
    const form = useForm({ ...fields, notes });
    const reworded = GROUPS.flatMap((group) => group.fields).filter(
        ([key]) => fields[key] !== original[key],
    );

    return (
        <>
            <Head title={`Voucher ${booking.reference}`} />
            <AdminPage>
                <PageHeader
                    title={`Voucher ${booking.reference}`}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <BookingStatusBadge booking={booking} />
                            {booking.sent_at
                                ? `Last sent ${formatBookingTime(booking.sent_at)}`
                                : 'Not sent yet'}
                        </span>
                    }
                    actions={
                        can('bookings.view') && (
                            <Button asChild variant="outline">
                                <Link
                                    href={BookingController.show.url(
                                        booking.reference,
                                    )}
                                >
                                    Open booking
                                </Link>
                            </Button>
                        )
                    }
                />

                {isVoid ? (
                    <Alert variant="destructive">
                        <Ban />
                        <AlertTitle>This voucher is void</AlertTitle>
                        <AlertDescription>
                            The booking was{' '}
                            {booking.status === 'no_show'
                                ? 'marked a no-show'
                                : 'cancelled'}
                            . The voucher is kept for the record and the renter
                            can no longer download it.
                        </AlertDescription>
                    </Alert>
                ) : (
                    !manageable && (
                        <Alert>
                            <Info />
                            <AlertTitle>This hire is finished</AlertTitle>
                            <AlertDescription>
                                The voucher is kept for the record and can no
                                longer be edited or sent.
                            </AlertDescription>
                        </Alert>
                    )
                )}

                <div className="flex flex-wrap gap-2">
                    {canEdit && (
                        <ConfirmDialog
                            trigger={
                                <Button
                                    disabled={form.isDirty || !booking.email}
                                >
                                    <Mail />
                                    {booking.sent_at
                                        ? 'Send again'
                                        : 'Send to the renter'}
                                </Button>
                            }
                            title={
                                booking.sent_at
                                    ? 'Send the voucher again?'
                                    : 'Send the voucher?'
                            }
                            description={`${booking.email} gets the confirmation with this voucher attached and a new booking link; earlier links stop working.`}
                            confirmLabel="Send"
                            onConfirm={() =>
                                new Promise((resolve) =>
                                    router.post(
                                        BookingVoucherController.resend.url(
                                            booking.reference,
                                        ),
                                        {},
                                        {
                                            preserveScroll: true,
                                            onFinish: resolve,
                                        },
                                    ),
                                )
                            }
                        />
                    )}
                    {/* The browser's PDF viewer prints it; the CSP stops it being embedded here. */}
                    {voucher ? (
                        <>
                            <Button asChild variant="outline">
                                <a
                                    href={voucher.urls.view}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Printer /> View or print
                                </a>
                            </Button>
                            <Button asChild variant="outline">
                                <a href={voucher.urls.download}>
                                    <Download /> Download
                                </a>
                            </Button>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No PDF was made before this booking closed.
                        </p>
                    )}
                </div>
                {canEdit && form.isDirty && (
                    <p className="text-sm text-muted-foreground">
                        Save your changes before sending.
                    </p>
                )}
                {canEdit && !booking.email && (
                    <p className="text-sm text-muted-foreground">
                        This client has no email address. Download or print the
                        voucher for them instead.
                    </p>
                )}

                {reworded.length > 0 && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>
                            This voucher differs from the booking
                        </AlertTitle>
                        <AlertDescription>
                            Changed:{' '}
                            {reworded.map(([, label]) => label).join(', ')}. The
                            booking keeps its own figures, and handover uses
                            those. Use the booking's actions to change the
                            booking itself.
                        </AlertDescription>
                    </Alert>
                )}

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(
                            BookingVoucherController.update.url(
                                booking.reference,
                            ),
                            { preserveScroll: true },
                        );
                    }}
                    className="grid gap-4 lg:grid-cols-2"
                >
                    {GROUPS.map((group) => (
                        <Card key={group.title}>
                            <CardHeader>
                                <CardTitle>{group.title}</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                {group.fields.map(([key, label]) => (
                                    <TextField
                                        key={key}
                                        name={key}
                                        label={label}
                                        disabled={!canEdit}
                                        value={form.data[key]}
                                        error={form.errors[key]}
                                        onChange={(event) =>
                                            form.setData(
                                                key,
                                                event.target.value,
                                            )
                                        }
                                    />
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Notes</CardTitle>
                            <CardDescription>
                                Shown on the voucher, for anything else the
                                client needs to know. Leave a field empty to go
                                back to the booking's own value.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <TextareaField
                                name="notes"
                                label="Notes"
                                rows={3}
                                disabled={!canEdit}
                                value={form.data.notes}
                                error={form.errors.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                            {canEdit && (
                                <div>
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing || !form.isDirty
                                        }
                                    >
                                        Save voucher
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </form>
            </AdminPage>
        </>
    );
}

BookingVoucher.layout = {
    breadcrumbs: [
        { title: 'Vouchers', href: VoucherController.index() },
        { title: 'Voucher', href: '#' },
    ],
};
