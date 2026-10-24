import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import AvailabilityController from '@/actions/App/Http/Controllers/Admin/AvailabilityController';
import BookingController from '@/actions/App/Http/Controllers/Admin/BookingController';
import { NativeSelect } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { formatBookingTime } from '@/lib/booking';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

type Block = {
    id: number;
    car_id: number;
    type: 'hold' | 'booked' | 'blocked';
    starts_at: string;
    ends_at: string;
    reason: string | null;
    notes: string | null;
    booking: { reference: string | null; status_label: string } | null;
};

type Filters = { start: string; days: number; car: string | null };

const DAY_MS = 24 * 60 * 60 * 1000;

const STYLES: Record<Block['type'], string> = {
    booked: 'bg-primary text-primary-foreground',
    hold: 'border border-dashed border-primary bg-primary/15 text-foreground',
    blocked: 'bg-muted-foreground/70 text-background',
};

const LEGEND: [Block['type'], string][] = [
    ['booked', 'Booked'],
    ['hold', 'Held, awaiting payment'],
    ['blocked', 'Blocked by staff'],
];

const dayLabel = new Intl.DateTimeFormat('en-KE', {
    weekday: 'short',
    day: 'numeric',
    timeZone: 'Africa/Nairobi',
});

/**
 * The availability calendar (M11.9): cars down the side, days across, and a bar for every
 * hold, booking and staff block. Times are the business time zone; the start is midnight.
 */
export default function AvailabilityIndex({
    filters,
    start,
    days,
    turnaroundMinutes,
    cars,
    carOptions,
    blocks,
}: {
    filters: Filters;
    start: string;
    days: number;
    turnaroundMinutes: number;
    cars: { id: number; name: string; slug: string; published: boolean }[];
    carOptions: Option[];
    blocks: Block[];
}) {
    const from = new Date(start).getTime();
    const span = days * DAY_MS;
    const columns = Array.from(
        { length: days },
        (_, i) => new Date(from + i * DAY_MS),
    );
    const today = Date.now();
    const todayIndex = Math.floor((today - from) / DAY_MS);

    const go = (changes: Partial<Filters>) =>
        router.get(
            AvailabilityController.index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, ...changes }).filter(
                    ([, v]) => v !== null && v !== '',
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const shift = (by: number) => {
        const date = new Date(from + by * DAY_MS + 3 * 60 * 60 * 1000);
        go({ start: date.toISOString().slice(0, 10) });
    };

    return (
        <>
            <Head title="Availability" />
            <AdminPage>
                <PageHeader
                    title="Availability"
                    description="Holds, bookings and staff blocks for every car. Select a bar to open its booking."
                />
                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Earlier"
                        onClick={() => shift(-days)}
                    >
                        <ChevronLeft />
                    </Button>
                    <Button variant="outline" onClick={() => go({ start: '' })}>
                        Today
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Later"
                        onClick={() => shift(days)}
                    >
                        <ChevronRight />
                    </Button>
                    <NativeSelect
                        aria-label="Days shown"
                        className="w-32"
                        value={String(days)}
                        options={[
                            { value: '14', label: '2 weeks' },
                            { value: '28', label: '4 weeks' },
                        ]}
                        onChange={(e) => go({ days: Number(e.target.value) })}
                    />
                    <NativeSelect
                        aria-label="Car"
                        className="sm:w-56"
                        placeholder="All cars"
                        options={carOptions}
                        value={filters.car ?? ''}
                        onChange={(e) => go({ car: e.target.value })}
                    />
                </div>
                <ul className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                    {LEGEND.map(([type, label]) => (
                        <li key={type} className="flex items-center gap-1.5">
                            <span
                                className={cn(
                                    'inline-block h-3 w-5 rounded-sm',
                                    STYLES[type],
                                )}
                            />
                            {label}
                        </li>
                    ))}
                    {turnaroundMinutes > 0 && (
                        <li>
                            Turnaround between hires: {turnaroundMinutes} min
                        </li>
                    )}
                </ul>

                <div className="overflow-x-auto rounded-md border">
                    <div
                        className="grid min-w-[48rem]"
                        style={{
                            gridTemplateColumns: `minmax(9rem, 12rem) repeat(${days}, minmax(2rem, 1fr))`,
                        }}
                    >
                        <div className="sticky left-0 z-10 border-b bg-background px-3 py-2 text-xs font-medium">
                            Car
                        </div>
                        {columns.map((day, i) => (
                            <div
                                key={i}
                                className={cn(
                                    'border-b border-l px-1 py-2 text-center text-xs text-muted-foreground',
                                    i === todayIndex &&
                                        'bg-accent font-medium text-foreground',
                                )}
                            >
                                {dayLabel.format(day)}
                            </div>
                        ))}

                        {cars.length === 0 && (
                            <p className="col-span-full p-6 text-sm text-muted-foreground">
                                No cars.
                            </p>
                        )}

                        {cars.map((car) => (
                            <div key={car.id} className="contents">
                                <div className="sticky left-0 z-10 flex items-center border-b bg-background px-3 py-2 text-sm">
                                    <span
                                        className={cn(
                                            'truncate',
                                            !car.published &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {car.name}
                                    </span>
                                </div>
                                <div
                                    className="relative h-11 border-b"
                                    style={{ gridColumn: `span ${days}` }}
                                >
                                    <div
                                        className="absolute inset-0 grid"
                                        style={{
                                            gridTemplateColumns: `repeat(${days}, 1fr)`,
                                        }}
                                    >
                                        {columns.map((_, i) => (
                                            <div
                                                key={i}
                                                className={cn(
                                                    'border-l',
                                                    i === todayIndex &&
                                                        'bg-accent/50',
                                                )}
                                            />
                                        ))}
                                    </div>
                                    {blocks
                                        .filter(
                                            (block) => block.car_id === car.id,
                                        )
                                        .map((block) => (
                                            <Bar
                                                key={block.id}
                                                block={block}
                                                from={from}
                                                span={span}
                                            />
                                        ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </AdminPage>
        </>
    );
}

function Bar({
    block,
    from,
    span,
}: {
    block: Block;
    from: number;
    span: number;
}) {
    const start = Math.max(new Date(block.starts_at).getTime(), from);
    const end = Math.min(new Date(block.ends_at).getTime(), from + span);
    const left = ((start - from) / span) * 100;
    const width = Math.max(((end - start) / span) * 100, 0.5);
    const label = block.booking?.reference ?? block.reason ?? 'Blocked';
    const title = [
        label,
        block.booking?.status_label,
        `${formatBookingTime(block.starts_at)} → ${formatBookingTime(block.ends_at)}`,
        block.notes,
    ]
        .filter(Boolean)
        .join(' · ');
    const className = cn(
        'absolute top-2 bottom-2 flex items-center overflow-hidden rounded px-1.5 text-xs whitespace-nowrap',
        STYLES[block.type],
    );
    const style = { left: `${left}%`, width: `${width}%` };

    return block.booking?.reference ? (
        <Link
            href={BookingController.show.url(block.booking.reference)}
            className={cn(className, 'hover:ring-2 hover:ring-ring')}
            style={style}
            title={title}
        >
            {label}
        </Link>
    ) : (
        <div className={className} style={style} title={title}>
            {label}
        </div>
    );
}

AvailabilityIndex.layout = {
    breadcrumbs: [
        { title: 'Availability', href: AvailabilityController.index() },
    ],
};
