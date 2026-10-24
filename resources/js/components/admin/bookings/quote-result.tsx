import { AlertTriangle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { percentFromBp } from '@/lib/format';
import type { AdminQuote, Money } from '@/types';

const nairobiTime = new Intl.DateTimeFormat('en-KE', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Africa/Nairobi',
});

function amount(money: Money | null | undefined): string {
    return money?.display ?? '—';
}

/**
 * A quote as staff read it: the lines, totals and deposit, then the amounts in each display
 * currency at the rates used. Shared by the quote sandbox and a booking's frozen quote (M11.2).
 */
export function QuoteResult({ quote }: { quote: AdminQuote }) {
    const currencies = Object.entries(quote.display);

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>
                        {quote.days} billable day{quote.days === 1 ? '' : 's'}
                    </CardTitle>
                    <CardDescription>
                        {nairobiTime.format(new Date(quote.pickup_at))} →{' '}
                        {nairobiTime.format(new Date(quote.return_at))} ·{' '}
                        {String(quote.settings.day_counting).replace(/_/g, ' ')}
                        {' · '}
                        {quote.mileage_km_per_day === null
                            ? 'unlimited mileage'
                            : `${quote.mileage_km_per_day} km/day`}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {quote.requires_staff_quote && (
                        <Alert className="mb-4">
                            <AlertTriangle />
                            <AlertDescription>
                                Delivery to this zone is quoted by staff and is
                                not in the total.
                            </AlertDescription>
                        </Alert>
                    )}
                    <Table>
                        <TableBody>
                            {quote.lines.map((line) => (
                                <TableRow key={`${line.kind}-${line.code}`}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {line.label}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {line.unit &&
                                                `${line.quantity} × ${line.unit.display}`}
                                            {line.note && ` · ${line.note}`}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {line.counted ? (
                                            amount(line.amount)
                                        ) : (
                                            <span className="text-muted-foreground">
                                                {amount(line.amount)}{' '}
                                                <Badge variant="outline">
                                                    not in total
                                                </Badge>
                                            </span>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                        <TableFooter>
                            <Summary label="Total" money={quote.total} />
                            <Summary
                                label={`Reservation (${percentFromBp(Number(quote.settings.reservation_percent_bp))})`}
                                money={quote.reservation}
                            />
                            <Summary
                                label="Balance at handover"
                                money={quote.balance}
                            />
                            <Summary
                                label={
                                    quote.deposit_band
                                        ? `Security deposit (${percentFromBp(quote.deposit_band.percent_bp)} of value, age ${quote.driver_age})`
                                        : 'Security deposit'
                                }
                                money={quote.deposit}
                                note={
                                    quote.deposit_hidden
                                        ? 'Needs permission to view car values'
                                        : quote.driver_age === null
                                          ? 'Enter a date of birth'
                                          : undefined
                                }
                            />
                        </TableFooter>
                    </Table>
                </CardContent>
            </Card>

            {currencies.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>In other currencies</CardTitle>
                        <CardDescription>
                            KES ÷ (market rate − margin), as renters see it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Currency</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Reservation
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Balance
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Deposit
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {currencies.map(([code, amounts]) => (
                                    <TableRow key={code}>
                                        <TableCell>
                                            <span className="font-mono">
                                                {code}
                                            </span>{' '}
                                            <span className="text-xs text-muted-foreground">
                                                at{' '}
                                                {Number(
                                                    quote.fx.rates[code]
                                                        ?.effective,
                                                )}
                                                {quote.fx.rates[code]?.stale &&
                                                    ' (stale)'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {amount(amounts.total)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {amount(amounts.reservation)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {amount(amounts.balance)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {amount(amounts.deposit)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            )}

            <p className="text-xs break-all text-muted-foreground">
                Settings hash {quote.settings_hash}
            </p>
        </>
    );
}

function Summary({
    label,
    money,
    note,
}: {
    label: string;
    money: Money | null;
    note?: string;
}) {
    return (
        <TableRow>
            <TableCell>
                {label}
                {note && (
                    <span className="block text-xs font-normal text-muted-foreground">
                        {note}
                    </span>
                )}
            </TableCell>
            <TableCell className="text-right tabular-nums">
                {amount(money)}
            </TableCell>
        </TableRow>
    );
}
