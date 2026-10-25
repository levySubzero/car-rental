import { Head, Link, useForm } from '@inertiajs/react';
import { Link2, X } from 'lucide-react';
import { useState } from 'react';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import GatewayReceiptController from '@/actions/App/Http/Controllers/Admin/GatewayReceiptController';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import {
    NativeSelect,
    TextareaField,
    TextField,
} from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
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
import { useCan } from '@/hooks/use-can';
import { formatDateTime } from '@/lib/format';
import type { AdminReceipt, Paginated } from '@/types';

type Filters = { search?: string; scope?: 'open' | 'all' };

/**
 * Online receipts (M11B.9): paybill payments whose account number named no booking, and
 * online payments held back because they could not be applied safely. Staff attach each to
 * the right booking, or close it as refunded or with a note. Oldest first while open.
 */
export default function ReceiptsIndex({
    receipts,
    filters,
    openCount,
}: {
    receipts: Paginated<AdminReceipt>;
    filters: Filters;
    openCount: number;
}) {
    const apply = useTableQuery(filters);
    const can = useCan();

    const columns: Column<AdminReceipt>[] = [
        {
            key: 'payment',
            header: 'Payment',
            cell: (receipt) => (
                <div className="grid gap-0.5">
                    <span className="font-medium">
                        {receipt.amount.display}
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            · {receipt.gateway_label} {receipt.receipt}
                        </span>
                    </span>
                    {(receipt.payer_name || receipt.payer_phone) && (
                        <span className="text-xs text-muted-foreground">
                            {[receipt.payer_name, receipt.payer_phone]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                    )}
                    {receipt.account !== null && (
                        <span className="text-xs text-muted-foreground">
                            Account number typed: {receipt.account || '(none)'}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'why',
            header: 'Why it is here',
            className: 'max-w-sm',
            cell: (receipt) => (
                <div className="grid gap-1 text-sm">
                    {receipt.booking ? (
                        <Link
                            href={BookingController.show.url(
                                receipt.booking.reference,
                            )}
                            className="font-medium hover:underline"
                        >
                            {receipt.booking.reference}{' '}
                            <span className="font-normal text-muted-foreground">
                                ({receipt.booking.status_label})
                            </span>
                        </Link>
                    ) : (
                        <span className="text-muted-foreground">
                            No booking matched.
                        </span>
                    )}
                    {receipt.reason && (
                        <p className="text-muted-foreground">
                            {receipt.reason}
                        </p>
                    )}
                    {receipt.resolution_note && (
                        <p className="text-muted-foreground">
                            {receipt.resolved_by}: {receipt.resolution_note}
                        </p>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            className: 'hidden sm:table-cell',
            cell: (receipt) => (
                <Badge variant={receipt.open ? 'default' : 'secondary'}>
                    {receipt.status_label}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            header: 'Received',
            className: 'hidden md:table-cell whitespace-nowrap',
            cell: (receipt) => formatDateTime(receipt.created_at),
        },
        {
            key: 'actions',
            header: <span className="sr-only">Actions</span>,
            cell: (receipt) =>
                receipt.open &&
                can('payments.verify') && (
                    <div className="flex flex-wrap justify-end gap-2">
                        <AttachReceiptDialog receipt={receipt} />
                        <CloseReceiptDialog receipt={receipt} />
                    </div>
                ),
        },
    ];

    return (
        <>
            <Head title="Online receipts" />
            <AdminPage>
                <PageHeader
                    title="Online receipts"
                    description={`Online payments the system could not apply by itself. ${openCount} to resolve.`}
                />
                <DataTable
                    data={receipts}
                    columns={columns}
                    filters={filters}
                    rowKey={(receipt) => receipt.id}
                    searchPlaceholder="Search receipt, account number, name or booking"
                    empty={
                        (filters.scope ?? 'open') === 'open'
                            ? 'Nothing to resolve.'
                            : 'No online payments yet.'
                    }
                    toolbar={
                        <NativeSelect
                            aria-label="Show"
                            className="sm:w-48"
                            value={filters.scope ?? 'open'}
                            options={[
                                {
                                    value: 'open',
                                    label: `To resolve (${openCount})`,
                                },
                                { value: 'all', label: 'All online payments' },
                            ]}
                            onChange={(event) =>
                                apply({ scope: event.target.value })
                            }
                        />
                    }
                />
            </AdminPage>
        </>
    );
}

/**
 * Apply the money to the booking it was meant for. It reserves the booking when the full
 * reservation was paid and the booking still takes a payment; otherwise it stays here with
 * the reason.
 */
function AttachReceiptDialog({ receipt }: { receipt: AdminReceipt }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ booking: receipt.booking?.reference ?? '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(GatewayReceiptController.attach.url(receipt.id), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <Link2 /> {receipt.booking ? 'Apply' : 'Attach'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            Apply {receipt.amount.display} to a booking
                        </DialogTitle>
                        <DialogDescription>
                            The booking is reserved if the full reservation was
                            paid and it is still waiting for payment. Otherwise
                            the payment stays here with the reason.
                        </DialogDescription>
                    </DialogHeader>
                    <TextField
                        name="booking"
                        label="Booking reference"
                        required
                        placeholder="BE-2026-000123"
                        value={form.data.booking}
                        error={form.errors.booking}
                        onChange={(event) =>
                            form.setData('booking', event.target.value)
                        }
                    />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Apply payment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Close a receipt without applying it: refunded to the payer (paid back outside the system),
 * or closed with a note.
 */
function CloseReceiptDialog({ receipt }: { receipt: AdminReceipt }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ outcome: 'refunded' | 'ignored'; note: string }>({
        outcome: 'refunded',
        note: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(GatewayReceiptController.resolve.url(receipt.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <X /> Close
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            Close {receipt.gateway_label} {receipt.receipt}
                        </DialogTitle>
                        <DialogDescription>
                            Refund the payer yourself first; this only records
                            what was done.
                        </DialogDescription>
                    </DialogHeader>
                    <NativeSelect
                        aria-label="Outcome"
                        value={form.data.outcome}
                        options={[
                            {
                                value: 'refunded',
                                label: 'Refunded to the payer',
                            },
                            { value: 'ignored', label: 'Closed, no refund' },
                        ]}
                        onChange={(event) =>
                            form.setData(
                                'outcome',
                                event.target.value as 'refunded' | 'ignored',
                            )
                        }
                    />
                    <TextareaField
                        name="note"
                        label="Note"
                        required
                        rows={3}
                        placeholder={
                            form.data.outcome === 'refunded'
                                ? 'Refund reference, e.g. M-Pesa reversal code.'
                                : 'Why it is closed, e.g. a duplicate.'
                        }
                        value={form.data.note}
                        error={form.errors.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Close receipt
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

ReceiptsIndex.layout = {
    breadcrumbs: [
        { title: 'Online receipts', href: GatewayReceiptController.index() },
    ],
};
