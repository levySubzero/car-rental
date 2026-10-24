import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import PaymentSubmissionController from '@/actions/App/Http/Controllers/Admin/PaymentSubmissionController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { adminNav } from '@/config/admin-nav';
import { useCan } from '@/hooks/use-can';
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

/**
 * The desk's day (M11.10): pickups and returns due, what is late, payments to check, then
 * the setup checklist (stale rates, unfilled settings). Each part needs its permission.
 */
export default function Dashboard({
    setupChecklist,
    today,
    paymentsWaiting,
}: {
    setupChecklist: SetupChecklistItem[];
    today: Today | null;
    paymentsWaiting: number | null;
}) {
    const { auth } = usePage().props;
    const can = useCan();
    const shortcuts = adminNav
        .flatMap((group) => group.items)
        .filter((item) => !item.exact && can(item.permission));

    return (
        <>
            <Head title="Dashboard" />
            <AdminPage>
                <PageHeader
                    title={`Welcome, ${auth.user.name}`}
                    description="Manage the Baines Executives website and bookings."
                />
                {(today || paymentsWaiting !== null) && (
                    <div className="grid gap-4 md:grid-cols-2">
                        {paymentsWaiting !== null && paymentsWaiting > 0 && (
                            <Link
                                href={PaymentSubmissionController.index()}
                                className="md:col-span-2"
                            >
                                <Card className="transition-colors hover:bg-accent">
                                    <CardHeader className="flex flex-row items-center justify-between">
                                        <CardTitle>
                                            {paymentsWaiting} payment
                                            {paymentsWaiting === 1
                                                ? ''
                                                : 's'}{' '}
                                            to check
                                        </CardTitle>
                                        <ChevronRight className="size-4 text-muted-foreground" />
                                    </CardHeader>
                                </Card>
                            </Link>
                        )}
                        {today && today.late_pickups.length > 0 && (
                            <DayList
                                title="Pickup time passed"
                                description="Hand over, or mark a no-show."
                                bookings={today.late_pickups}
                                at="pickup"
                                urgent
                            />
                        )}
                        {today && today.overdue_returns.length > 0 && (
                            <DayList
                                title="Overdue returns"
                                description="Due back already."
                                bookings={today.overdue_returns}
                                at="return"
                                urgent
                            />
                        )}
                        {today && (
                            <DayList
                                title="Pickups today"
                                bookings={today.pickups}
                                at="pickup"
                            />
                        )}
                        {today && (
                            <DayList
                                title="Returns today"
                                bookings={today.returns}
                                at="return"
                            />
                        )}
                    </div>
                )}
                {setupChecklist.length > 0 && (
                    <SetupChecklist items={setupChecklist} />
                )}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {shortcuts.map((item) => (
                        <Link key={item.title} href={item.href} prefetch>
                            <Card className="h-full transition-colors hover:bg-accent">
                                <CardHeader>
                                    {item.icon && (
                                        <item.icon className="size-5 text-muted-foreground" />
                                    )}
                                    <CardTitle>{item.title}</CardTitle>
                                    <CardDescription>
                                        Open {item.title.toLowerCase()}
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        </Link>
                    ))}
                </div>
            </AdminPage>
        </>
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
 * What is still missing before launch (M5.11): only items the viewer can fix are listed.
 */
function SetupChecklist({ items }: { items: SetupChecklistItem[] }) {
    return (
        <Card className="max-w-4xl">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    Before launch
                    <Badge variant="secondary">{items.length}</Badge>
                </CardTitle>
                <CardDescription>
                    Details the website still needs. Each item disappears once
                    it is filled in.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul className="divide-y rounded-md border">
                    {items.map((item) => (
                        <li key={item.key}>
                            <Link
                                href={item.url}
                                className="flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-accent"
                            >
                                <Badge
                                    variant="outline"
                                    className="hidden w-32 justify-center sm:inline-flex"
                                >
                                    {item.area}
                                </Badge>
                                <div className="grid min-w-0 flex-1">
                                    <span className="truncate text-sm font-medium">
                                        {item.label}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {item.detail}
                                    </span>
                                </div>
                                <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                            </Link>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
