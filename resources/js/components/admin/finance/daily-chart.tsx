import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';

const shortDate = new Intl.DateTimeFormat('en-KE', {
    day: 'numeric',
    month: 'short',
});

function dayLabel(date: string): string {
    // Dates arrive as local (Nairobi) `Y-m-d`; read them as calendar days, not instants.
    return shortDate.format(new Date(`${date}T12:00:00`));
}

/**
 * One bar per day. `format` renders a value for the tooltip and the axis, such as KES or a
 * plain count.
 */
export function DailyChart({
    data,
    label,
    format,
    compact,
}: {
    data: { date: string; value: number }[];
    label: string;
    format: (value: number) => string;
    // Shorter axis labels for small values such as counts.
    compact?: (value: number) => string;
}) {
    const config = {
        value: { label, color: 'var(--chart-1)' },
    } satisfies ChartConfig;

    return (
        <ChartContainer config={config} className="aspect-auto h-56 w-full">
            <BarChart data={data} margin={{ left: 4, right: 4 }}>
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="date"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    minTickGap={24}
                    tickFormatter={dayLabel}
                />
                <YAxis
                    tickLine={false}
                    axisLine={false}
                    width={56}
                    allowDecimals={false}
                    tickFormatter={compact ?? format}
                />
                <ChartTooltip
                    cursor={false}
                    content={
                        <ChartTooltipContent
                            labelFormatter={(_, payload) =>
                                dayLabel(
                                    String(payload[0]?.payload?.date ?? ''),
                                )
                            }
                            formatter={(value) => (
                                <span className="flex w-full justify-between gap-4">
                                    <span className="text-muted-foreground">
                                        {label}
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {format(Number(value))}
                                    </span>
                                </span>
                            )}
                        />
                    }
                />
                <Bar
                    dataKey="value"
                    fill="var(--color-value)"
                    radius={[3, 3, 0, 0]}
                />
            </BarChart>
        </ChartContainer>
    );
}

// Axis labels for money: "12k", "1.2M" shillings.
export function compactKes(minor: number): string {
    return new Intl.NumberFormat('en-KE', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(minor / 100);
}
