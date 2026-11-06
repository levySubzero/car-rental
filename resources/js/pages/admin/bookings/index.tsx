import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import BookingCreateController from '@/actions/App/Http/Controllers/Admin/BookingCreateController';
import { BookingStatusBadge } from '@/components/admin/bookings/booking-status-badge';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCan } from '@/hooks/use-can';
import { formatBookingTime } from '@/lib/booking';
import { cn } from '@/lib/utils';
import type { AdminBookingRow, Option, Paginated } from '@/types';

type Filters = {
    bucket?: string;
    search?: string;
    from?: string;
    to?: string;
    car?: string;
    method?: string;
};

/**
 * The bookings desk (M11.1): tabs by what staff do next, soonest pickup first. The Requests
 * screen (admin/bookings/requests) is the same table over the requests' tabs.
 */
export default function BookingsIndex({
    bookings,
    filters,
    counts,
    buckets,
    cars,
    methods,
    requests = false,
}: {
    bookings: Paginated<AdminBookingRow>;
    filters: Filters;
    counts: Record<string, number>;
    buckets: Option[];
    cars: Option[];
    methods: Option[];
    requests?: boolean;
}) {
    const can = useCan();
    const apply = useTableQuery(filters);
    const bucket = filters.bucket ?? buckets[0]?.value;
    const title = requests ? 'Requests' : 'Bookings';
    const tabUrl = requests
        ? BookingController.requests.url
        : BookingController.index.url;

    const columns: Column<AdminBookingRow>[] = [
        {
            key: 'reference',
            header: 'Booking',
            cell: (booking) => (
                <div className="grid">
                    <Link
                        href={BookingController.show.url(booking.reference)}
                        className="font-medium hover:underline"
                    >
                        {booking.reference}
                    </Link>
                    <span className="text-xs text-muted-foreground">
                        {booking.renter ?? '—'} · {booking.car}
                    </span>
                    {booking.phone && (
                        <span className="text-xs text-muted-foreground">
                            {booking.phone}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'dates',
            header: 'Dates',
            className: 'whitespace-nowrap',
            cell: (booking) => (
                <div className="grid text-sm">
                    <span>{formatBookingTime(booking.pickup_at)}</span>
                    <span className="text-muted-foreground">
                        to {formatBookingTime(booking.return_at)}
                    </span>
                    {(booking.pickup_mode === 'address' ||
                        booking.return_mode === 'address') && (
                        <span className="text-xs text-muted-foreground">
                            Delivery
                            {booking.delivery_pending && (
                                <Badge variant="destructive" className="ml-1">
                                    fee to quote
                                </Badge>
                            )}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (booking) => (
                <div className="flex flex-wrap gap-1">
                    <BookingStatusBadge booking={booking} />
                    {booking.one_day && (
                        <Badge variant="secondary">One day</Badge>
                    )}
                    {booking.voucher_unsent && (
                        <Badge variant="destructive">Voucher not sent</Badge>
                    )}
                    {booking.car_taken && (
                        <Badge variant="destructive">Car taken</Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'money',
            header: 'Total',
            className: 'hidden md:table-cell text-right tabular-nums',
            cell: (booking) => (
                <div className="grid">
                    <span>{booking.total.display}</span>
                    <span className="text-xs text-muted-foreground">
                        balance {booking.balance.display}
                        {booking.method && ` · ${booking.method}`}
                    </span>
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title={title} />
            <AdminPage>
                <PageHeader
                    title={title}
                    description={
                        requests
                            ? 'Website requests to accept or decline, and accepted ones waiting for payment.'
                            : 'Paid and confirmed bookings, from pick-up to return.'
                    }
                    actions={
                        !requests &&
                        can('bookings.create') && (
                            <Button asChild>
                                <Link href={BookingCreateController.create()}>
                                    <Plus /> Enter a booking
                                </Link>
                            </Button>
                        )
                    }
                />
                <nav
                    className="flex gap-1 overflow-x-auto border-b"
                    aria-label="Booking tabs"
                >
                    {buckets.map((tab) => (
                        <Link
                            key={tab.value}
                            href={tabUrl({ query: { bucket: tab.value } })}
                            preserveState
                            aria-current={
                                tab.value === bucket ? 'page' : undefined
                            }
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors',
                                tab.value === bucket
                                    ? 'border-primary font-medium text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}{' '}
                            <span className="text-muted-foreground tabular-nums">
                                {counts[tab.value] ?? 0}
                            </span>
                        </Link>
                    ))}
                </nav>
                <DataTable
                    data={bookings}
                    columns={columns}
                    filters={filters}
                    rowKey={(booking) => booking.id}
                    searchPlaceholder="Search reference, name or phone"
                    empty="No bookings here."
                    toolbar={
                        <div className="grid gap-2 sm:flex sm:flex-wrap">
                            <Input
                                type="date"
                                aria-label="Pickup from"
                                className="sm:w-40"
                                value={filters.from ?? ''}
                                onChange={(e) =>
                                    apply({ from: e.target.value })
                                }
                            />
                            <Input
                                type="date"
                                aria-label="Pickup to"
                                className="sm:w-40"
                                value={filters.to ?? ''}
                                onChange={(e) => apply({ to: e.target.value })}
                            />
                            <NativeSelect
                                aria-label="Car"
                                className="sm:w-48"
                                placeholder="All cars"
                                options={cars}
                                value={filters.car ?? ''}
                                onChange={(e) => apply({ car: e.target.value })}
                            />
                            <NativeSelect
                                aria-label="Payment method"
                                className="sm:w-44"
                                placeholder="All methods"
                                options={methods}
                                value={filters.method ?? ''}
                                onChange={(e) =>
                                    apply({ method: e.target.value })
                                }
                            />
                        </div>
                    }
                />
            </AdminPage>
        </>
    );
}

BookingsIndex.layout = {
    breadcrumbs: [{ title: 'Bookings', href: BookingController.index() }],
};
