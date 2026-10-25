import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

// KES minor units, whole shillings unless there are cents.
export function kes(minor: number): string {
    return formatMoney(minor, { symbol: 'KES', decimals: 0 });
}

/**
 * One figure with its label. With `href` the whole tile links to the menu behind it.
 */
export function StatTile({
    label,
    value,
    hint,
    icon: Icon,
    href,
    tone,
}: {
    label: string;
    value: ReactNode;
    hint?: ReactNode;
    icon?: LucideIcon;
    href?: InertiaLinkProps['href'];
    tone?: 'attention';
}) {
    const tile = (
        <Card
            className={cn(
                'h-full gap-0 py-4 transition-colors',
                href && 'hover:bg-accent',
                tone === 'attention' && 'border-l-4 border-l-primary',
            )}
        >
            <CardContent className="grid gap-1 px-4">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-sm font-medium text-muted-foreground">
                        {label}
                    </span>
                    {Icon && <Icon className="size-4 text-accent-gold" />}
                </div>
                <span className="text-2xl font-semibold tracking-tight tabular-nums">
                    {value}
                </span>
                {hint && (
                    <span className="text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </CardContent>
        </Card>
    );

    return href ? (
        <Link href={href} prefetch className="block">
            {tile}
        </Link>
    ) : (
        tile
    );
}
