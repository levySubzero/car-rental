import { Head, Link } from '@inertiajs/react';
import BookingVoucherController from '@/actions/App/Http/Controllers/Admin/BookingVoucherController';
import VoucherController from '@/actions/App/Http/Controllers/Admin/VoucherController';
import { BookingStatusBadge } from '@/components/admin/bookings/booking-status-badge';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { formatBookingTime } from '@/lib/booking';
import { cn } from '@/lib/utils';
import type { AdminVoucherRow, Option, Paginated } from '@/types';

type Filters = {
    bucket?: string;
    search?: string;
    from?: string;
    to?: string;
    car?: string;
};

/**
 * Every confirmed booking's voucher, by where it stands: waiting to go out, sent, used, or
 * void after a cancellation or no-show. Each opens on its voucher page.
 */
export default function VouchersIndex({
    vouchers,
    filters,
    counts,
    buckets,
    cars,
}: {
    vouchers: Paginated<AdminVoucherRow>;
    filters: Filters;
    counts: Record<string, number>;
    buckets: Option[];
    cars: Option[];
}) {
    const apply = useTableQuery(filters);
    const bucket = filters.bucket ?? buckets[0]?.value;

    const columns: Column<AdminVoucherRow>[] = [
        {
            key: 'reference',
            header: 'Voucher',
            cell: (voucher) => (
                <div className="grid">
                    <Link
                        href={BookingVoucherController.show.url(
                            voucher.reference,
                        )}
                        className="font-medium hover:underline"
                    >
                        {voucher.reference}
                    </Link>
                    <span className="text-xs text-muted-foreground">
                        {voucher.renter ?? '—'} · {voucher.car}
                    </span>
                    {voucher.phone && (
                        <span className="text-xs text-muted-foreground">
                            {voucher.phone}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'dates',
            header: 'Dates',
            className: 'whitespace-nowrap',
            cell: (voucher) => (
                <div className="grid text-sm">
                    <span>{formatBookingTime(voucher.pickup_at)}</span>
                    <span className="text-muted-foreground">
                        to {formatBookingTime(voucher.return_at)}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Booking',
            cell: (voucher) => <BookingStatusBadge booking={voucher} />,
        },
        {
            key: 'sent',
            header: 'Sent',
            className: 'whitespace-nowrap text-sm',
            cell: (voucher) =>
                voucher.sent_at ? (
                    formatBookingTime(voucher.sent_at)
                ) : voucher.by_hand ? (
                    <Badge variant="secondary">By hand</Badge>
                ) : (
                    <span className="text-muted-foreground">Not sent</span>
                ),
        },
        {
            key: 'total',
            header: 'Total',
            className: 'hidden md:table-cell text-right tabular-nums',
            cell: (voucher) => voucher.total.display,
        },
    ];

    return (
        <>
            <Head title="Vouchers" />
            <AdminPage>
                <PageHeader
                    title="Vouchers"
                    description="Each confirmed booking's voucher: what still needs sending, what went out, and what no longer stands."
                />
                <nav
                    className="flex gap-1 overflow-x-auto border-b"
                    aria-label="Voucher tabs"
                >
                    {buckets.map((tab) => (
                        <Link
                            key={tab.value}
                            href={VoucherController.index.url({
                                query: { bucket: tab.value },
                            })}
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
                    data={vouchers}
                    columns={columns}
                    filters={filters}
                    rowKey={(voucher) => voucher.id}
                    searchPlaceholder="Search reference, name or phone"
                    empty="No vouchers here."
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
                        </div>
                    }
                />
            </AdminPage>
        </>
    );
}

VouchersIndex.layout = {
    breadcrumbs: [{ title: 'Vouchers', href: VoucherController.index() }],
};
