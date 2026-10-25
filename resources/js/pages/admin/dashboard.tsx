import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    ChevronRight,
    Gauge,
    Inbox,
    Landmark,
    ListChecks,
    MessageSquare,
    ReceiptText,
    Wallet,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import BookingRequestController from '@/actions/App/Http/Controllers/Admin/BookingRequestController';
import ContactMessageController from '@/actions/App/Http/Controllers/Admin/ContactMessageController';
import FinanceController from '@/actions/App/Http/Controllers/Admin/FinanceController';
import GatewayReceiptController from '@/actions/App/Http/Controllers/Admin/GatewayReceiptController';
import PaymentSubmissionController from '@/actions/App/Http/Controllers/Admin/PaymentSubmissionController';
import SetupReminderController from '@/actions/App/Http/Controllers/Admin/SetupReminderController';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatBookingTime } from '@/lib/booking';
import { dashboard } from '@/routes/admin';
import type { SetupChecklistItem } from '@/types';

type DayBooking = {
    reference: string;
    car: string;
    renter: string | null;
    phone: string | null;
    pickup_at: string;
    return_at: string;
    pickup_mode: 'office' | 'address';
    return_mode: 'office' | 'address';
};

type Today = {
    pickups: DayBooking[];
    returns: DayBooking[];
    late_pickups: DayBooking[];
    overdue_returns: DayBooking[];
};

type Series = { date: string; value: number }[];

// Each is null when the viewer lacks its permission (DashboardController).
type Widgets = {
    revenue: { this_month_minor: number; last_month_minor: number } | null;
    collected: Series | null;
    bookings: { this_month: number; last_month: number } | null;
    bookingsSeries: Series | null;
    utilisation: {
        percent: number;
        booked_days: number;
        car_days: number;
    } | null;
    topCars: { car_id: number; car: string; days: number }[] | null;
    paymentsWaiting: number | null;
    receiptsOpen: number | null;
    openRequests: number | null;
    newMessages: number | null;
};

function plural(count: number, one: string, many = `${one}s`): string {
    return `${count} ${count === 1 ? one : many}`;
}

function versusLastMonth(
    current: number,
    previous: number,
    format: (value: number) => string,
): string {
    if (previous === 0) {
        return `${format(previous)} by this day last month`;
    }

    const change = Math.round(
        ((current - previous) / Math.abs(previous)) * 100,
    );

    return `${change >= 0 ? '+' : ''}${change}% on ${format(previous)} by this day last month`;
}

/**
 * Figures and charts for what the viewer can see (M11C.6), each linking to its menu; the
 * desk's day (M11.10); and the setup checklist, a popup that opens once per session
 * (M11B.10). Each part needs its permission.
 */
export default function Dashboard({
    setupChecklist,
    setupReminderDismissed,
    today,
    widgets,
}: {
    setupChecklist: SetupChecklistItem[];
    setupReminderDismissed: boolean;
    today: Today | null;
    widgets: Widgets;
}) {
    const { auth } = usePage().props;
    const {
        revenue,
        collected,
        bookings,
        bookingsSeries,
        utilisation,
        topCars,
        paymentsWaiting,
        receiptsOpen,
        openRequests,
        newMessages,
    } = widgets;
    const hasTiles = [
        revenue,
        bookings,
        utilisation,
        paymentsWaiting,
        receiptsOpen,
        openRequests,
        newMessages,
    ].some((widget) => widget !== null);
    const hasCharts = collected !== null || bookingsSeries !== null;
    const isEmpty = !hasTiles && !hasCharts && today === null;

    return (
        <>
            <Head title="Dashboard" />
            <AdminPage>
                <PageHeader
                    title={`Welcome, ${auth.user.name}`}
                    description="Manage the Baines Executives website and bookings."
                    actions={
                        setupChecklist.length > 0 && (
                            <SetupReminder
                                items={setupChecklist}
                                dismissed={setupReminderDismissed}
                            />
                        )
                    }
                />

                {hasTiles && (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {revenue && (
                            <StatTile
                                label="Revenue this month"
                                value={kes(revenue.this_month_minor)}
                                hint={versusLastMonth(
                                    revenue.this_month_minor,
                                    revenue.last_month_minor,
                                    kes,
                                )}
                                icon={Landmark}
                                href={FinanceController.index()}
                            />
                        )}
                        {bookings && (
                            <StatTile
                                label="Bookings this month"
                                value={bookings.this_month}
                                hint={versusLastMonth(
                                    bookings.this_month,
                                    bookings.last_month,
                                    String,
                                )}
                                icon={CalendarCheck}
                                href={BookingController.index()}
                            />
                        )}
                        {utilisation && (
                            <StatTile
                                label="Fleet utilisation"
                                value={`${utilisation.percent}%`}
                                hint={`${utilisation.booked_days} of ${utilisation.car_days} car-days booked, last 30 days`}
                                icon={Gauge}
                                href={BookingController.index()}
                            />
                        )}
                        {paymentsWaiting !== null && (
                            <StatTile
                                label="Payments to check"
                                value={paymentsWaiting}
                                hint={
                                    paymentsWaiting > 0
                                        ? 'Waiting for verification'
                                        : 'All checked'
                                }
                                icon={Wallet}
                                href={PaymentSubmissionController.index()}
                                tone={
                                    paymentsWaiting > 0
                                        ? 'attention'
                                        : undefined
                                }
                            />
                        )}
                        {receiptsOpen !== null && (
                            <StatTile
                                label="Receipts to resolve"
                                value={receiptsOpen}
                                hint={
                                    receiptsOpen > 0
                                        ? 'Online payments needing review'
                                        : 'Nothing to resolve'
                                }
                                icon={ReceiptText}
                                href={GatewayReceiptController.index()}
                                tone={
                                    receiptsOpen > 0 ? 'attention' : undefined
                                }
                            />
                        )}
                        {openRequests !== null && (
                            <StatTile
                                label="Open requests"
                                value={openRequests}
                                hint="Changes and cancellations from renters"
                                icon={MessageSquare}
                                href={BookingRequestController.index()}
                                tone={
                                    openRequests > 0 ? 'attention' : undefined
                                }
                            />
                        )}
                        {newMessages !== null && (
                            <StatTile
                                label="New messages"
                                value={newMessages}
                                hint="From the contact form"
                                icon={Inbox}
                                href={ContactMessageController.index()}
                                tone={newMessages > 0 ? 'attention' : undefined}
                            />
                        )}
                    </div>
                )}

                {today && (
                    <div className="grid gap-4 md:grid-cols-2">
                        {today.late_pickups.length > 0 && (
                            <DayList
                                title="Pickup time passed"
                                description="Hand over, or mark a no-show."
                                bookings={today.late_pickups}
                                at="pickup"
                                urgent
                            />
                        )}
                        {today.overdue_returns.length > 0 && (
                            <DayList
                                title="Overdue returns"
                                description="Due back already."
                                bookings={today.overdue_returns}
                                at="return"
                                urgent
                            />
                        )}
                        <DayList
                            title="Pickups today"
                            bookings={today.pickups}
                            at="pickup"
                        />
                        <DayList
                            title="Returns today"
                            bookings={today.returns}
                            at="return"
                        />
                    </div>
                )}

                {hasCharts && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {collected && (
                            <ChartCard
                                title="Collected per day"
                                description="Last 30 days"
                                href={FinanceController.index()}
                            >
                                <DailyChart
                                    data={collected}
                                    label="Collected"
                                    format={kes}
                                    compact={compactKes}
                                />
                            </ChartCard>
                        )}
                        {bookingsSeries && (
                            <ChartCard
                                title="Bookings per day"
                                description="Submitted, last 30 days"
                                href={BookingController.index()}
                            >
                                <DailyChart
                                    data={bookingsSeries}
                                    label="Bookings"
                                    format={String}
                                />
                            </ChartCard>
                        )}
                    </div>
                )}

                {topCars && (
                    <Card className="max-w-2xl">
                        <CardHeader>
                            <CardTitle>Most booked cars</CardTitle>
                            <CardDescription>
                                Booked days, last 30 days
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {topCars.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No cars booked in the last 30 days.
                                </p>
                            ) : (
                                <ul className="grid gap-3">
                                    {topCars.map((row) => (
                                        <li
                                            key={row.car_id}
                                            className="grid gap-1"
                                        >
                                            <div className="flex justify-between gap-2 text-sm">
                                                <span className="truncate font-medium">
                                                    {row.car}
                                                </span>
                                                <span className="text-muted-foreground tabular-nums">
                                                    {plural(row.days, 'day')}
                                                </span>
                                            </div>
                                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary"
                                                    style={{
                                                        width: `${Math.min(100, (row.days / 30) * 100)}%`,
                                                    }}
                                                />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                )}

                {isEmpty && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Nothing to show here yet</CardTitle>
                            <CardDescription>
                                Use the menu to open the parts of the admin you
                                work in.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}
            </AdminPage>
        </>
    );
}

function ChartCard({
    title,
    description,
    href,
    children,
}: {
    title: string;
    description: string;
    href: Parameters<typeof Link>[0]['href'];
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between gap-2">
                <div className="grid gap-1.5">
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </div>
                <Link
                    href={href}
                    prefetch
                    className="flex items-center gap-1 text-sm font-medium text-accent-gold hover:underline"
                >
                    Open <ChevronRight className="size-4" />
                </Link>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function DayList({
    title,
    description,
    bookings,
    at,
    urgent = false,
}: {
    title: string;
    description?: string;
    bookings: DayBooking[];
    at: 'pickup' | 'return';
    urgent?: boolean;
}) {
    return (
        <Card className={urgent ? 'border-destructive/50' : undefined}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    {title}
                    <Badge variant={urgent ? 'destructive' : 'secondary'}>
                        {bookings.length}
                    </Badge>
                </CardTitle>
                {description && (
                    <CardDescription>{description}</CardDescription>
                )}
            </CardHeader>
            <CardContent>
                {bookings.length === 0 ? (
                    <p className="text-sm text-muted-foreground">None.</p>
                ) : (
                    <ul className="divide-y rounded-md border">
                        {bookings.map((booking) => (
                            <li key={booking.reference}>
                                <Link
                                    href={BookingController.show.url(
                                        booking.reference,
                                    )}
                                    className="flex items-center gap-3 px-3 py-2 text-sm hover:bg-accent"
                                >
                                    <span className="grid min-w-0 flex-1">
                                        <span className="font-medium">
                                            {formatBookingTime(
                                                at === 'pickup'
                                                    ? booking.pickup_at
                                                    : booking.return_at,
                                            )}{' '}
                                            · {booking.reference}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {booking.renter ?? '—'} ·{' '}
                                            {booking.car}
                                            {(at === 'pickup'
                                                ? booking.pickup_mode
                                                : booking.return_mode) ===
                                                'address' &&
                                                (at === 'pickup'
                                                    ? ' · delivery'
                                                    : ' · collection')}
                                        </span>
                                    </span>
                                    <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * What is still missing before launch (M5.11, M11B.10): only items the viewer can fix are
 * listed. It opens by itself until closed once in the session; the button reopens it.
 */
function SetupReminder({
    items,
    dismissed,
}: {
    items: SetupChecklistItem[];
    dismissed: boolean;
}) {
    const [open, setOpen] = useState(!dismissed);

    const change = (next: boolean) => {
        setOpen(next);

        if (!next && !dismissed) {
            router.post(
                SetupReminderController.url(),
                {},
                { preserveScroll: true, preserveState: true },
            );
        }
    };

    return (
        <>
            <Button variant="outline" size="sm" onClick={() => change(true)}>
                <ListChecks /> Before launch
                <Badge variant="secondary">{items.length}</Badge>
            </Button>
            <Dialog open={open} onOpenChange={change}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Before launch</DialogTitle>
                        <DialogDescription>
                            Details the website still needs. Each item
                            disappears once it is filled in.
                        </DialogDescription>
                    </DialogHeader>
                    <ul className="divide-y rounded-md border">
                        {items.map((item) => (
                            <li key={item.key}>
                                <Link
                                    href={item.url}
                                    onClick={(event) => {
                                        // Going to fix an item counts as closing the popup.
                                        if (!dismissed) {
                                            event.preventDefault();
                                            router.post(
                                                SetupReminderController.url(),
                                                {},
                                                {
                                                    onFinish: () =>
                                                        router.visit(item.url),
                                                },
                                            );
                                        }
                                    }}
                                    className="flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-accent"
                                >
                                    <Badge
                                        variant="outline"
                                        className="hidden w-32 justify-center sm:inline-flex"
                                    >
                                        {item.area}
                                    </Badge>
                                    <div className="grid min-w-0 flex-1">
                                        <span className="text-sm font-medium">
                                            {item.label}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {item.detail}
                                        </span>
                                    </div>
                                    <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </DialogContent>
            </Dialog>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
