import { Head } from '@inertiajs/react';
import PaymentSubmissionController from '@/actions/App/Http/Controllers/Admin/PaymentSubmissionController';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import {
    RejectPaymentDialog,
    VerifyPaymentDialog,
} from '@/components/admin/payments/payment-dialogs';
import { SecureDocumentViewer } from '@/components/media/secure-document-viewer';
import { Badge } from '@/components/ui/badge';
import { useCan } from '@/hooks/use-can';
import { formatDateTime } from '@/lib/format';
import type { AdminPayment, Option, Paginated } from '@/types';

type Filters = { search?: string; status?: string };

/**
 * The payments queue (M9): renter payments to match against the M-Pesa, bank and card
 * statements, plus cash bookings waiting at the office. Oldest first while pending.
 */
export default function PaymentsIndex({
    payments,
    filters,
    counts,
    statusOptions,
}: {
    payments: Paginated<AdminPayment>;
    filters: Filters;
    counts: Record<string, number>;
    statusOptions: Option[];
}) {
    const apply = useTableQuery(filters);
    const can = useCan();

    const columns: Column<AdminPayment>[] = [
        {
            key: 'booking',
            header: 'Booking',
            cell: (payment) => (
                <div className="grid">
                    <span className="font-medium">
                        {payment.booking.reference}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {payment.booking.renter} · {payment.booking.car}
                    </span>
                    {payment.booking.phone && (
                        <span className="text-xs text-muted-foreground">
                            {payment.booking.phone}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'payment',
            header: 'Payment',
            className: 'max-w-md',
            cell: (payment) => (
                <div className="grid gap-1.5">
                    <span className="text-sm">
                        {payment.method_name}
                        {payment.online && (
                            <Badge variant="outline" className="ml-2">
                                {payment.online.label}
                            </Badge>
                        )}
                        <span className="text-muted-foreground">
                            {' '}
                            · due {payment.booking.reservation.display}
                        </span>
                    </span>
                    {payment.transaction_message && (
                        <p className="line-clamp-3 text-sm break-words whitespace-pre-line text-muted-foreground">
                            {payment.transaction_message}
                        </p>
                    )}
                    {payment.method === 'cash' &&
                        payment.status === 'pending' && (
                            <p className="text-sm text-muted-foreground">
                                Paying cash at the office
                                {payment.booking.expires_at &&
                                    `, held until ${formatDateTime(payment.booking.expires_at)}`}
                                .
                            </p>
                        )}
                    {payment.proof && (
                        <SecureDocumentViewer
                            media={payment.proof}
                            label="Proof"
                        />
                    )}
                    {payment.status === 'verified' && payment.amount && (
                        <p className="text-sm">
                            {payment.amount.display} · ref {payment.reference}
                            <span className="text-muted-foreground">
                                {' '}
                                · {payment.verified_by},{' '}
                                {formatDateTime(payment.verified_at)}
                            </span>
                            {payment.note && (
                                <span className="block text-muted-foreground">
                                    {payment.note}
                                </span>
                            )}
                        </p>
                    )}
                    {payment.status === 'rejected' && (
                        <p className="text-sm text-muted-foreground">
                            Rejected by {payment.rejected_by}:{' '}
                            {payment.rejection_reason}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            className: 'hidden sm:table-cell',
            cell: (payment) => (
                <Badge
                    variant={
                        payment.status === 'pending' ? 'default' : 'secondary'
                    }
                >
                    {payment.status_label}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            header: 'Sent',
            className: 'hidden md:table-cell whitespace-nowrap',
            cell: (payment) => formatDateTime(payment.created_at),
        },
        {
            key: 'actions',
            header: <span className="sr-only">Actions</span>,
            cell: (payment) =>
                payment.status === 'pending' && (
                    <div className="flex flex-wrap justify-end gap-2">
                        {can('payments.verify') && (
                            <VerifyPaymentDialog payment={payment} />
                        )}
                        {can('payments.reject') &&
                            payment.method !== 'cash' && (
                                <RejectPaymentDialog payment={payment} />
                            )}
                    </div>
                ),
        },
    ];

    return (
        <>
            <Head title="Payments" />
            <AdminPage>
                <PageHeader
                    title="Payments"
                    description={`Reservation payments to check against your statements. ${counts.pending ?? 0} waiting.`}
                />
                <DataTable
                    data={payments}
                    columns={columns}
                    filters={filters}
                    rowKey={(payment) => payment.id}
                    searchPlaceholder="Search reference, name, phone or message"
                    empty="No payments."
                    toolbar={
                        <NativeSelect
                            aria-label="Status"
                            className="sm:w-48"
                            value={filters.status ?? 'pending'}
                            options={statusOptions.map((option) => ({
                                ...option,
                                label: `${option.label} (${counts[option.value] ?? 0})`,
                            }))}
                            onChange={(event) =>
                                apply({ status: event.target.value })
                            }
                        />
                    }
                />
            </AdminPage>
        </>
    );
}

PaymentsIndex.layout = {
    breadcrumbs: [
        { title: 'Payments', href: PaymentSubmissionController.index() },
    ],
};
