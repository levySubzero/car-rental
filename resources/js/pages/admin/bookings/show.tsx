import { Head, router } from '@inertiajs/react';
import { AlertTriangle, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import {
    CloseBookingDialog,
    DeliveryCheckedButton,
    DeliveryDialog,
    HandoverDialog,
    MarkRefundedDialog,
    ReturnDialog,
    VoucherActions,
} from '@/components/admin/bookings/booking-dialogs';
import { BookingStatusBadge } from '@/components/admin/bookings/booking-status-badge';
import { QuoteResult } from '@/components/admin/bookings/quote-result';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import {
    RejectPaymentDialog,
    VerifyPaymentDialog,
} from '@/components/admin/payments/payment-dialogs';
import { SecureDocumentViewer } from '@/components/media/secure-document-viewer';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { formatDateTime, humanize, percentFromBp } from '@/lib/format';
import type {
    AdminBooking,
    AdminHandoverRecord,
    BookingLeg,
    BookingTimelineEntry,
    Option,
    RefundSuggestion,
} from '@/types';

/**
 * One booking on the desk (M11.2): everything staff need, and the actions its status and
 * their permissions allow.
 */
export default function BookingShow({
    booking,
    timeline,
    refund,
    zones,
    fuelLevels,
}: {
    booking: AdminBooking;
    timeline: BookingTimelineEntry[];
    refund: { cancel: RefundSuggestion; no_show: RefundSuggestion } | null;
    zones: (Option & { priced: boolean })[];
    fuelLevels: Option[];
}) {
    const can = useCan();
    const status = booking.status;
    const pendingPayment = booking.payments.find((p) => p.status === 'pending');
    const deliveryEditable = [
        'awaiting_payment',
        'payment_submitted',
        'awaiting_cash',
        'payment_rejected',
        'reserved',
    ].includes(status);

    return (
        <>
            <Head title={booking.reference} />
            <AdminPage>
                <PageHeader
                    title={booking.reference}
                    description={
                        <span className="flex flex-wrap items-center gap-2">
                            <BookingStatusBadge booking={booking} />
                            {booking.car.name} ·{' '}
                            {formatBookingTime(booking.pickup_at)} to{' '}
                            {formatBookingTime(booking.return_at)}
                        </span>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {pendingPayment && can('payments.verify') && (
                        <VerifyPaymentDialog payment={pendingPayment} />
                    )}
                    {pendingPayment &&
                        pendingPayment.method !== 'cash' &&
                        can('payments.reject') && (
                            <RejectPaymentDialog payment={pendingPayment} />
                        )}
                    {status === 'reserved' &&
                        can('bookings.record-handover') && (
                            <HandoverDialog
                                booking={booking}
                                fuelLevels={fuelLevels}
                            />
                        )}
                    {status === 'active' && can('bookings.record-return') && (
                        <ReturnDialog
                            booking={booking}
                            fuelLevels={fuelLevels}
                        />
                    )}
                    {can('bookings.resend-voucher') &&
                        ![
                            'completed',
                            'cancelled',
                            'no_show',
                            'expired',
                        ].includes(status) && (
                            <VoucherActions booking={booking} />
                        )}
                    {refund && can('bookings.cancel') && (
                        <CloseBookingDialog
                            booking={booking}
                            suggestion={refund.cancel}
                        />
                    )}
                    {refund &&
                        status === 'reserved' &&
                        can('bookings.cancel') && (
                            <CloseBookingDialog
                                booking={booking}
                                suggestion={refund.no_show}
                                noShow
                            />
                        )}
                    {booking.cancellation &&
                        Number(booking.cancellation.refund.minor) > 0 &&
                        !booking.cancellation.refunded_at &&
                        can('bookings.cancel') && (
                            <MarkRefundedDialog booking={booking} />
                        )}
                </div>

                {booking.balance_outstanding && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertDescription>
                            <p>
                                <strong>
                                    {booking.balance_outstanding.display} still
                                    owing.
                                </strong>{' '}
                                {booking.handover?.balance_received?.display ??
                                    'Nothing'}{' '}
                                of the {booking.balance.display} balance was
                                taken at handover.
                            </p>
                            {booking.handover?.notes && (
                                <p>Handover note: {booking.handover.notes}</p>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {booking.requests.some((r) => r.status === 'open') && (
                    <Alert>
                        <AlertTriangle />
                        <AlertDescription>
                            {booking.requests
                                .filter((r) => r.status === 'open')
                                .map((r) => (
                                    <p key={r.id}>
                                        <strong>{r.type_label} request</strong>{' '}
                                        ({formatDateTime(r.created_at)}):{' '}
                                        {r.message}
                                    </p>
                                ))}
                            <p className="mt-1">
                                Act on it here, then mark it handled under
                                Requests.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="grid min-w-0 content-start gap-6 lg:col-span-2">
                        <Section title="Trip">
                            <Facts
                                rows={[
                                    [
                                        'Pickup',
                                        `${formatBookingTime(booking.pickup_at)} · ${booking.pickup_mode_label}`,
                                    ],
                                    [
                                        'Return',
                                        `${formatBookingTime(booking.return_at)} · ${booking.return_mode_label}`,
                                    ],
                                    ['Flight', booking.flight_number],
                                    [
                                        'Submitted',
                                        formatDateTime(booking.submitted_at),
                                    ],
                                    [
                                        'Terms accepted',
                                        formatDateTime(
                                            booking.accepted_terms_at,
                                        ),
                                    ],
                                ]}
                            />
                        </Section>

                        {booking.delivery && (
                            <Section
                                title="Delivery"
                                description="Check the address really is in the zone the renter chose (D33)."
                                actions={
                                    deliveryEditable &&
                                    can('bookings.update-delivery') && (
                                        <>
                                            {!booking.delivery.checked_at && (
                                                <DeliveryCheckedButton
                                                    booking={booking}
                                                />
                                            )}
                                            <DeliveryDialog
                                                booking={booking}
                                                zones={zones}
                                            />
                                        </>
                                    )
                                }
                            >
                                {booking.delivery.pending && (
                                    <Alert
                                        variant="destructive"
                                        className="mb-4"
                                    >
                                        <AlertTriangle />
                                        <AlertDescription>
                                            This zone is quoted by staff. Set
                                            the delivery fee before handover.
                                        </AlertDescription>
                                    </Alert>
                                )}
                                <Facts
                                    rows={[
                                        ['Zone', booking.delivery.zone?.name],
                                        [
                                            'Covers',
                                            booking.delivery.zone?.description,
                                        ],
                                        [
                                            'Fee',
                                            booking.delivery.fee?.display ??
                                                'Not set',
                                        ],
                                        [
                                            'Checked',
                                            booking.delivery.checked_at
                                                ? formatDateTime(
                                                      booking.delivery
                                                          .checked_at,
                                                  )
                                                : 'Not yet',
                                        ],
                                    ]}
                                />
                                {booking.pickup_mode === 'address' && (
                                    <Leg
                                        title="Delivered to"
                                        leg={booking.delivery.pickup}
                                    />
                                )}
                                {booking.return_mode === 'address' && (
                                    <Leg
                                        title="Collected from"
                                        leg={booking.delivery.return}
                                    />
                                )}
                            </Section>
                        )}

                        <Section title="Money">
                            <Facts
                                rows={[
                                    ['Total', booking.total.display],
                                    [
                                        'Reservation due',
                                        booking.reservation.display,
                                    ],
                                    [
                                        'Reservation paid',
                                        booking.reservation_paid?.display ??
                                            'Nothing yet',
                                    ],
                                    [
                                        'Balance at handover',
                                        booking.balance.display,
                                    ],
                                    [
                                        'Still owing',
                                        booking.balance_outstanding?.display,
                                    ],
                                    [
                                        'Security deposit',
                                        booking.security_deposit?.display,
                                    ],
                                ]}
                            />
                        </Section>

                        {booking.quote && <QuoteResult quote={booking.quote} />}

                        <Section title="Payments">
                            {booking.payments.length === 0 ? (
                                <Empty>No payment submitted.</Empty>
                            ) : (
                                <ul className="divide-y">
                                    {booking.payments.map((payment) => (
                                        <li
                                            key={payment.id}
                                            className="grid gap-1 py-3 text-sm first:pt-0"
                                        >
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium">
                                                    {payment.method_name}
                                                </span>
                                                {payment.online && (
                                                    <Badge variant="outline">
                                                        {payment.online.label}
                                                    </Badge>
                                                )}
                                                <Badge
                                                    variant={
                                                        payment.status ===
                                                        'pending'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {payment.status_label}
                                                </Badge>
                                                <span className="text-muted-foreground">
                                                    {formatDateTime(
                                                        payment.created_at,
                                                    )}
                                                </span>
                                            </div>
                                            {payment.transaction_message && (
                                                <p className="break-words whitespace-pre-line text-muted-foreground">
                                                    {
                                                        payment.transaction_message
                                                    }
                                                </p>
                                            )}
                                            {payment.proof && (
                                                <SecureDocumentViewer
                                                    media={payment.proof}
                                                    label="Proof"
                                                />
                                            )}
                                            {payment.status === 'verified' && (
                                                <p>
                                                    {payment.amount?.display} ·
                                                    ref {payment.reference} ·{' '}
                                                    {payment.verified_by}
                                                    {payment.note && (
                                                        <span className="block text-muted-foreground">
                                                            {payment.note}
                                                        </span>
                                                    )}
                                                </p>
                                            )}
                                            {payment.status === 'rejected' && (
                                                <p className="text-muted-foreground">
                                                    Rejected
                                                    {payment.rejected_by &&
                                                        ` by ${payment.rejected_by}`}
                                                    : {payment.rejection_reason}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Section>

                        {(booking.handover || booking.return) && (
                            <Section title="Handover and return">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {booking.handover && (
                                        <Record
                                            title="Handed over"
                                            record={booking.handover}
                                        />
                                    )}
                                    {booking.return && (
                                        <Record
                                            title="Returned"
                                            record={booking.return}
                                        />
                                    )}
                                </div>
                            </Section>
                        )}

                        <Section title="History">
                            <ol className="grid gap-3 text-sm">
                                {timeline.map((entry, index) => (
                                    <li key={index} className="grid">
                                        <span>
                                            {humanize(entry.from)} →{' '}
                                            <strong>
                                                {humanize(entry.to)}
                                            </strong>
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDateTime(entry.at)} ·{' '}
                                            {entry.causer ?? entry.actor}
                                            {entry.reason &&
                                                ` · ${entry.reason}`}
                                        </span>
                                    </li>
                                ))}
                            </ol>
                        </Section>
                    </div>

                    <div className="grid min-w-0 content-start gap-6">
                        <Section title="Driver">
                            {booking.driver ? (
                                <>
                                    <Facts
                                        rows={[
                                            ['Name', booking.driver.name],
                                            ['Phone', booking.driver.phone],
                                            ['Email', booking.driver.email],
                                            ['Born', booking.driver.dob],
                                            [
                                                'Nationality',
                                                `${booking.driver.nationality}${booking.driver.is_kenyan_resident ? ', Kenyan resident' : ''}`,
                                            ],
                                            [
                                                booking.driver.id_type,
                                                booking.driver.id_number,
                                            ],
                                            ['KRA PIN', booking.driver.kra_pin],
                                            [
                                                'Licence',
                                                booking.driver.licence_number,
                                            ],
                                            [
                                                'Licence expires',
                                                booking.driver.licence_expiry,
                                            ],
                                            [
                                                'Driving for',
                                                `${booking.driver.licence_years} years`,
                                            ],
                                        ]}
                                    />
                                    {booking.driver.masked && (
                                        <p className="mt-3 text-xs text-muted-foreground">
                                            Identity numbers are masked: viewing
                                            them needs the documents permission.
                                        </p>
                                    )}
                                </>
                            ) : (
                                <Empty>No driver details.</Empty>
                            )}
                        </Section>

                        <Section title="Documents">
                            {booking.kyc_purged_at ? (
                                <Empty>
                                    KYC purged on{' '}
                                    {formatDateTime(booking.kyc_purged_at)}.
                                </Empty>
                            ) : booking.documents === null ? (
                                <Empty>
                                    You need the documents permission to see
                                    these.
                                </Empty>
                            ) : booking.documents.length === 0 ? (
                                <Empty>No documents uploaded.</Empty>
                            ) : (
                                <ul className="grid gap-2">
                                    {booking.documents.map((doc) => (
                                        <li
                                            key={doc.key}
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <span>{doc.label}</span>
                                            <SecureDocumentViewer
                                                media={doc.file}
                                                label="View"
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {booking.voucher && (
                                <div className="mt-4 flex items-center justify-between gap-2 border-t pt-4 text-sm">
                                    <span>Voucher</span>
                                    <SecureDocumentViewer
                                        media={booking.voucher}
                                        label="Open"
                                    />
                                </div>
                            )}
                        </Section>

                        {booking.cancellation && (
                            <Section
                                title={
                                    status === 'no_show'
                                        ? 'No-show'
                                        : 'Cancellation'
                                }
                            >
                                <Facts
                                    rows={[
                                        ['Reason', booking.cancellation.reason],
                                        [
                                            'Tier',
                                            booking.cancellation.tier
                                                ? `${booking.cancellation.tier.label ?? `${booking.cancellation.tier.hours_before_pickup} h or more`} (${percentFromBp(booking.cancellation.tier.refund_percent_bp)})`
                                                : 'None met',
                                        ],
                                        [
                                            'Suggested refund',
                                            booking.cancellation.suggested
                                                .display,
                                        ],
                                        [
                                            'Refund',
                                            booking.cancellation.refund.display,
                                        ],
                                        [
                                            'Why changed',
                                            booking.cancellation
                                                .override_reason,
                                        ],
                                        [
                                            'Paid back',
                                            booking.cancellation.refunded_at
                                                ? `${formatDateTime(booking.cancellation.refunded_at)} · ref ${booking.cancellation.reference}`
                                                : Number(
                                                        booking.cancellation
                                                            .refund.minor,
                                                    ) > 0
                                                  ? 'Not yet'
                                                  : null,
                                        ],
                                    ]}
                                />
                            </Section>
                        )}

                        <Section title="Requests">
                            {booking.requests.length === 0 ? (
                                <Empty>No requests from the renter.</Empty>
                            ) : (
                                <ul className="grid gap-3 text-sm">
                                    {booking.requests.map((r) => (
                                        <li key={r.id}>
                                            <span className="font-medium">
                                                {r.type_label}
                                            </span>{' '}
                                            <Badge
                                                variant={
                                                    r.status === 'open'
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {r.status}
                                            </Badge>
                                            <p className="text-muted-foreground">
                                                {r.message}
                                            </p>
                                            {r.staff_note && (
                                                <p className="text-xs">
                                                    Staff: {r.staff_note}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Section>

                        {can('bookings.legal-hold') &&
                            !booking.kyc_purged_at && (
                                <Section title="Retention">
                                    <p className="mb-3 text-sm text-muted-foreground">
                                        {booking.legal_hold
                                            ? 'On legal hold: KYC is kept past the retention period.'
                                            : 'KYC is deleted after the retention period once the booking ends.'}
                                    </p>
                                    <LegalHoldButton booking={booking} />
                                </Section>
                            )}
                    </div>
                </div>
            </AdminPage>
        </>
    );
}

function LegalHoldButton({ booking }: { booking: AdminBooking }) {
    const toggle = (reason: string | null) =>
        new Promise((resolve) =>
            router.put(
                BookingController.legalHold.url(booking.reference),
                { legal_hold: !booking.legal_hold, reason },
                { preserveScroll: true, onFinish: resolve },
            ),
        );

    return (
        <ConfirmDialog
            trigger={
                <Button size="sm" variant="outline">
                    <ShieldCheck />
                    {booking.legal_hold ? 'Lift legal hold' : 'Set legal hold'}
                </Button>
            }
            title={
                booking.legal_hold
                    ? 'Lift the legal hold?'
                    : 'Set a legal hold?'
            }
            description={
                booking.legal_hold
                    ? 'KYC will be deleted on the usual schedule.'
                    : "Keep this booking's KYC past the retention period, e.g. for a dispute or claim."
            }
            onConfirm={() =>
                toggle(booking.legal_hold ? null : 'Set from the bookings desk')
            }
        />
    );
}

function Section({
    title,
    description,
    actions,
    children,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-2">
                <div className="grid gap-1">
                    <CardTitle>{title}</CardTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </div>
                {actions && (
                    <div className="flex flex-wrap gap-2">{actions}</div>
                )}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function Facts({ rows }: { rows: [string, string | null | undefined][] }) {
    return (
        <dl className="grid grid-cols-[minmax(0,10rem)_1fr] gap-x-4 gap-y-1.5 text-sm">
            {rows
                .filter(
                    ([, value]) =>
                        value !== null && value !== undefined && value !== '',
                )
                .map(([label, value]) => (
                    <div key={label} className="contents">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="break-words">{value}</dd>
                    </div>
                ))}
        </dl>
    );
}

function Leg({ title, leg }: { title: string; leg: BookingLeg }) {
    return (
        <div className="mt-4 border-t pt-4">
            <p className="mb-2 text-sm font-medium">{title}</p>
            <Facts
                rows={[
                    ['Address', leg.address],
                    ['Landmark', leg.landmark],
                    ['Driver phone', leg.contact_phone],
                    [
                        'Pin',
                        leg.lat && leg.lng ? `${leg.lat}, ${leg.lng}` : null,
                    ],
                ]}
            />
            {leg.lat && leg.lng && (
                <a
                    className="mt-2 inline-block text-sm underline"
                    href={`https://www.google.com/maps?q=${leg.lat},${leg.lng}`}
                    target="_blank"
                    rel="noreferrer"
                >
                    Open the pin in Google Maps
                </a>
            )}
        </div>
    );
}

function Record({
    title,
    record,
}: {
    title: string;
    record: AdminHandoverRecord;
}) {
    return (
        <div>
            <p className="mb-2 text-sm font-medium">
                {title}{' '}
                <span className="font-normal text-muted-foreground">
                    {formatDateTime(record.recorded_at)} · {record.recorded_by}
                </span>
            </p>
            <Facts
                rows={[
                    ['Odometer', `${record.odometer_km} km`],
                    ['Fuel', record.fuel_label],
                    ['Balance taken', record.balance_received?.display],
                    ['Deposit taken', record.deposit_received?.display],
                    [
                        'Excess',
                        record.excess_km !== null
                            ? `${record.excess_km} km`
                            : null,
                    ],
                    ['Extra charges', record.extra_charges?.display],
                    ['Deposit refunded', record.deposit_refunded?.display],
                    ['Notes', record.notes],
                ]}
            />
        </div>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return <p className="text-sm text-muted-foreground">{children}</p>;
}

BookingShow.layout = {
    breadcrumbs: [{ title: 'Bookings', href: BookingController.index() }],
};
