import { router, usePage, usePoll } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Banknote,
    Bell,
    CalendarPlus,
    Mail,
    MessageSquare,
} from 'lucide-react';
import AlertController from '@/actions/App/Http/Controllers/Admin/AlertController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { AdminAlert } from '@/types/admin';

const ICONS: Record<string, LucideIcon> = {
    'calendar-plus': CalendarPlus,
    banknote: Banknote,
    mail: Mail,
    'message-square': MessageSquare,
};

const RELATIVE = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

function ago(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const minutes = Math.round((new Date(iso).getTime() - Date.now()) / 60_000);

    if (Math.abs(minutes) < 60) {
        return RELATIVE.format(minutes, 'minute');
    }

    const hours = Math.round(minutes / 60);

    return Math.abs(hours) < 24
        ? RELATIVE.format(hours, 'hour')
        : RELATIVE.format(Math.round(hours / 24), 'day');
}

/**
 * Staff alerts (M10B): new bookings, payments to check, enquiries and renter requests.
 * The count refreshes every minute while the tab is visible.
 */
export function NotificationBell() {
    const alerts = usePage().props.alerts;

    usePoll(60_000, { only: ['alerts'] });

    if (!alerts) {
        return null;
    }

    const open = (alert: AdminAlert) =>
        router.post(AlertController.open.url(alert.id));

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative size-9"
                    aria-label={
                        alerts.unread > 0
                            ? `Notifications, ${alerts.unread} unread`
                            : 'Notifications'
                    }
                >
                    <Bell className="size-5" />
                    {alerts.unread > 0 && (
                        <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-none font-semibold text-white tabular-nums">
                            {alerts.unread > 9 ? '9+' : alerts.unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-[min(22rem,calc(100vw-2rem))]"
            >
                <div className="flex items-center justify-between gap-2 pr-1">
                    <DropdownMenuLabel>Notifications</DropdownMenuLabel>
                    {alerts.unread > 0 && (
                        <Button
                            variant="link"
                            size="sm"
                            className="h-auto px-1 text-xs"
                            onClick={() =>
                                router.post(
                                    AlertController.readAll.url(),
                                    {},
                                    { preserveScroll: true, only: ['alerts'] },
                                )
                            }
                        >
                            Mark all read
                        </Button>
                    )}
                </div>
                <DropdownMenuSeparator />
                {alerts.latest.length === 0 ? (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                        Nothing new.
                    </p>
                ) : (
                    alerts.latest.map((alert) => {
                        const Icon = ICONS[alert.icon] ?? Bell;

                        return (
                            <DropdownMenuItem
                                key={alert.id}
                                onSelect={() => open(alert)}
                                className="items-start gap-3 py-2"
                            >
                                <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <span className="grid min-w-0 flex-1 gap-0.5">
                                    <span
                                        className={cn(
                                            'truncate text-sm',
                                            !alert.read && 'font-semibold',
                                        )}
                                    >
                                        {alert.title}
                                    </span>
                                    <span className="line-clamp-2 text-xs text-muted-foreground">
                                        {alert.body}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {ago(alert.created_at)}
                                    </span>
                                </span>
                                {!alert.read && (
                                    <span
                                        className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                        aria-label="Unread"
                                    />
                                )}
                            </DropdownMenuItem>
                        );
                    })
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
