import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownLeft,
    Clock,
    Download,
    Landmark,
    ShieldCheck,
    Wallet,
} from 'lucide-react';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import FinanceController from '@/actions/App/Http/Controllers/Admin/FinanceController';
import type { Column } from '@/components/admin/data-table';
import { DataTable, useTableQuery } from '@/components/admin/data-table';
import { compactKes, DailyChart } from '@/components/admin/finance/daily-chart';
import { kes, StatTile } from '@/components/admin/finance/stat-tile';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    FinanceCarTotal,
    FinanceChannelTotal,
    FinanceDay,
    FinanceSummary,
    LedgerEntry,
    Paginated,
} from '@/types';

type Filters = { from: string; to: string };

// Calendar dates in the business time zone, as the server counts days.
function nairobiDate(date: Date): string {
    return date.toLocaleDateString('en-CA', { timeZone: 'Africa/Nairobi' });
}

function presets(): { label: string; from: string; to: string }[] {
    const today = nairobiDate(new Date());
    const [year, month] = today.split('-').map(Number);
    const monthStart = (y: number, m: number) =>
        `${y}-${String(m).padStart(2, '0')}-01`;
    const lastMonth = month === 1 ? [year - 1, 12] : [year, month - 1];
    const lastMonthEnd = new Date(Date.UTC(year, month - 1, 0));
    const thirtyAgo = new Date(Date.now() - 29 * 24 * 60 * 60 * 1000);

    return [
        { label: 'This month', from: monthStart(year, month), to: today },
        {
            label: 'Last month',
            from: monthStart(lastMonth[0], lastMonth[1]),
            to: lastMonthEnd.toISOString().slice(0, 10),
        },
        { label: 'Last 30 days', from: nairobiDate(thirtyAgo), to: today },
    ];
}

/**
 * Finance → Revenue (M11C): money collected and refunded in a range of Nairobi days, worked
 * out from verified payments, handovers and refunds. Deposits are held, not revenue.
 */
export default function FinanceIndex({
    filters,
    summary,
    daily,
    channels,
    cars,
    ledger,
    canExport,
}: {
    filters: Filters;
    summary: FinanceSummary;
    daily: FinanceDay[];
    channels: FinanceChannelTotal[];
    cars: FinanceCarTotal[];
    ledger: Paginated<LedgerEntry>;
    canExport: boolean;
}) {
    const apply = useTableQuery(filters);
    const collected = channels.reduce((sum, row) => sum + row.amount_minor, 0);

    const columns: Column<LedgerEntry>[] = [
        {
            key: 'at',
            header: 'When',
            cell: (entry) => formatDateTime(entry.at),
        },
        {
            key: 'kind',
            header: 'Type',
            cell: (entry) => (
                <div className="grid">
                    <span className="font-medium">{entry.kind_label}</span>
                    {entry.channel_label && (
                        <span className="text-xs text-muted-foreground">
                            {entry.channel_label}
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'booking',
            header: 'Booking',
            cell: (entry) =>
                entry.booking_reference ? (
                    <div className="grid">
                        <Link
                            href={BookingController.show.url(
                                entry.booking_reference,
                            )}
                            className="font-medium text-accent-gold hover:underline"
                        >
                            {entry.booking_reference}
                        </Link>
                        {entry.car && (
                            <span className="text-xs text-muted-foreground">
                                {entry.car}
                            </span>
                        )}
                    </div>
                ) : (
                    '—'
                ),
        },
        {
            key: 'reference',
            header: 'Reference',
            cell: (entry) => entry.reference ?? '—',
        },
        {
            key: 'amount',
            header: <span className="block text-right">Amount</span>,
            className: 'text-right',
            cell: (entry) => (
                <span
                    className={cn(
                        'font-medium tabular-nums',
                        entry.amount_minor < 0 && 'text-destructive',
                    )}
                >
                    {kes(entry.amount_minor)}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title="Revenue" />
            <AdminPage>
                <PageHeader
                    title="Revenue"
                    description="Money collected and refunded, from verified payments, handovers and refunds. Days are Nairobi time."
                    actions={
                        canExport && (
                            <Button variant="outline" asChild>
                                <a
                                    href={FinanceController.export.url({
                                        query: filters,
                                    })}
                                >
                                    <Download />
                                    Export CSV
                                </a>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                    <div className="flex gap-2">
                        <Input
                            type="date"
                            aria-label="From"
                            className="w-full sm:w-40"
                            value={filters.from}
                            max={filters.to}
                            onChange={(event) =>
                                event.target.value &&
                                apply({ from: event.target.value })
                            }
                        />
                        <Input
                            type="date"
                            aria-label="To"
                            className="w-full sm:w-40"
                            value={filters.to}
                            min={filters.from}
                            onChange={(event) =>
                                event.target.value &&
                                apply({ to: event.target.value })
                            }
                        />
                    </div>
                    <div className="flex flex-wrap gap-1">
                        {presets().map((preset) => {
                            const active =
                                preset.from === filters.from &&
                                preset.to === filters.to;

                            return (
                                <Button
                                    key={preset.label}
                                    size="sm"
                                    variant={active ? 'default' : 'outline'}
                                    onClick={() =>
                                        apply({
                                            from: preset.from,
                                            to: preset.to,
                                        })
                                    }
                                >
                                    {preset.label}
                                </Button>
                            );
                        })}
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatTile
                        label="Collected"
                        value={kes(summary.collected_minor)}
                        icon={Wallet}
                    />
                    <StatTile
                        label="Refunds"
                        value={kes(summary.refunds_minor)}
                        icon={ArrowDownLeft}
                    />
                    <StatTile
                        label="Net"
                        value={kes(summary.net_minor)}
                        icon={Landmark}
                        tone="attention"
                    />
                    <StatTile
                        label="Deposits held"
                        value={kes(summary.deposits_held_minor)}
                        hint="Cars still out; not revenue"
                        icon={ShieldCheck}
                    />
                    <StatTile
                        label="Balances due"
                        value={kes(summary.outstanding_minor)}
                        hint={`${summary.outstanding_count} reserved ${summary.outstanding_count === 1 ? 'booking' : 'bookings'} not yet handed over`}
                        icon={Clock}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Collected per day</CardTitle>
                        <CardDescription>
                            Payments verified and money taken at handover and
                            return.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DailyChart
                            data={daily.map((day) => ({
                                date: day.date,
                                value: day.collected_minor,
                            }))}
                            label="Collected"
                            format={kes}
                            compact={compactKes}
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>By channel</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {channels.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nothing collected in this period.
                                </p>
                            ) : (
                                <ul className="grid gap-3">
                                    {channels.map((row) => (
                                        <li
                                            key={row.channel}
                                            className="grid gap-1"
                                        >
                                            <div className="flex items-center justify-between gap-2 text-sm">
                                                <span className="font-medium">
                                                    {row.label}{' '}
                                                    <Badge variant="secondary">
                                                        {row.count}
                                                    </Badge>
                                                </span>
                                                <span className="tabular-nums">
                                                    {kes(row.amount_minor)}
                                                </span>
                                            </div>
                                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary"
                                                    style={{
                                                        width: `${collected > 0 ? (row.amount_minor / collected) * 100 : 0}%`,
                                                    }}
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>By car</CardTitle>
                            <CardDescription>Top 10 by net.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {cars.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nothing collected in this period.
                                </p>
                            ) : (
                                <ul className="divide-y rounded-md border">
                                    {cars.map((row) => (
                                        <li
                                            key={row.car_id ?? row.car}
                                            className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                        >
                                            <span className="truncate font-medium">
                                                {row.car}
                                            </span>
                                            <span className="grid text-right tabular-nums">
                                                <span className="font-medium">
                                                    {kes(row.net_minor)}
                                                </span>
                                                {row.refunds_minor > 0 && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {kes(
                                                            row.collected_minor,
                                                        )}{' '}
                                                        less{' '}
                                                        {kes(row.refunds_minor)}{' '}
                                                        refunded
                                                    </span>
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <section className="grid gap-3">
                    <h2 className="font-display text-lg">Ledger</h2>
                    <DataTable
                        data={ledger}
                        columns={columns}
                        filters={filters}
                        rowKey={(entry) =>
                            `${entry.kind}-${entry.at}-${entry.booking_reference}`
                        }
                        empty="No money moved in this period."
                    />
                </section>
            </AdminPage>
        </>
    );
}

FinanceIndex.layout = {
    breadcrumbs: [{ title: 'Revenue', href: FinanceController.index() }],
};
