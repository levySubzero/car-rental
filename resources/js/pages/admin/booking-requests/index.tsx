import { Head, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import BookingRequestController from '@/actions/App/Http/Controllers/Admin/BookingRequestController';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect, TextareaField } from '@/components/admin/form-field';
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
import type { AdminBookingRequest, Option, Paginated } from '@/types';

type Filters = { search?: string; status?: string };

function HandleRequestDialog({ request }: { request: AdminBookingRequest }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ note: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(BookingRequestController.handle.url(request.id), {
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
                    <Check /> Mark handled
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {request.type_label} request for{' '}
                            {request.booking.reference}
                        </DialogTitle>
                        <DialogDescription>
                            Make the change or cancellation first, then record
                            what you did. The renter is not notified from here.
                        </DialogDescription>
                    </DialogHeader>
                    <TextareaField
                        name="note"
                        label="What was done (internal)"
                        rows={3}
                        value={form.data.note}
                        error={form.errors.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                    />
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Mark handled
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function BookingRequestsIndex({
    requests,
    filters,
    counts,
    statusOptions,
}: {
    requests: Paginated<AdminBookingRequest>;
    filters: Filters;
    counts: Record<string, number>;
    statusOptions: Option[];
}) {
    const apply = useTableQuery(filters);
    const can = useCan();

    const columns: Column<AdminBookingRequest>[] = [
        {
            key: 'booking',
            header: 'Booking',
            cell: (request) => (
                <div className="grid">
                    <span className="font-medium">
                        {request.booking.reference}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {request.booking.renter}
                        {request.booking.phone && `, ${request.booking.phone}`}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {request.booking.car}, pick-up{' '}
                        {formatDateTime(request.booking.pickup_at)}
                    </span>
                </div>
            ),
        },
        {
            key: 'message',
            header: 'Request',
            className: 'max-w-md',
            cell: (request) => (
                <div className="grid gap-1">
                    <Badge
                        variant={
                            request.type === 'cancel'
                                ? 'destructive'
                                : 'secondary'
                        }
                        className="w-fit"
                    >
                        {request.type_label}
                    </Badge>
                    <span className="text-sm whitespace-pre-line">
                        {request.message}
                    </span>
                    {request.staff_note && (
                        <span className="text-xs text-muted-foreground">
                            {request.handled_by}: {request.staff_note}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'created_at',
            header: 'Received',
            className: 'hidden sm:table-cell whitespace-nowrap',
            cell: (request) => formatDateTime(request.created_at),
        },
        {
            key: 'actions',
            header: '',
            className: 'text-right',
            cell: (request) =>
                request.status === 'open' &&
                can('booking-requests.update') && (
                    <HandleRequestDialog request={request} />
                ),
        },
    ];

    return (
        <>
            <Head title="Requests" />
            <AdminPage>
                <PageHeader
                    title="Requests"
                    description={`Cancellations and changes renters asked for on My bookings. ${counts.open ?? 0} open.`}
                />
                <DataTable
                    data={requests}
                    columns={columns}
                    filters={filters}
                    rowKey={(request) => request.id}
                    searchPlaceholder="Search booking reference"
                    empty="No requests."
                    toolbar={
                        <NativeSelect
                            aria-label="Status"
                            className="sm:w-48"
                            value={filters.status ?? 'open'}
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

BookingRequestsIndex.layout = {
    breadcrumbs: [
        { title: 'Requests', href: BookingRequestController.index() },
    ],
};
