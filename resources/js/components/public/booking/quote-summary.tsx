import { useState } from 'react';
import { useSite } from '@/hooks/use-site';
import { cn } from '@/lib/utils';
import type { PublicQuote } from '@/types';

/**
 * The quote's lines and totals (M8.13). Amounts are charged in KES; the renter can see the
 * totals in another currency, converted by the server at today's rate.
 */
export function QuoteSummary({
    quote,
    className,
}: {
    quote: PublicQuote;
    className?: string;
}) {
    const { currency } = useSite();
    // `display` holds the other currencies; the KES amounts are the quote's own.
    const totals: PublicQuote['display'] = {
        KES: {
            total: quote.total,
            reservation: quote.reservation,
            balance: quote.balance,
            deposit: quote.deposit,
        },
        ...quote.display,
    };
    const codes = Object.keys(totals);
    const [code, setCode] = useState(
        codes.includes(currency.selected) ? currency.selected : 'KES',
    );
    const shown = totals[code];

    return (
        <div className={cn('rounded-lg bg-surface p-6', className)}>
            <div className="flex items-center justify-between gap-4">
                <h3 className="text-lg font-semibold text-foreground">
                    {quote.days} {quote.days === 1 ? 'day' : 'days'}
                </h3>
                {codes.length > 1 && (
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        Show in
                        <select
                            value={code}
                            onChange={(event) => setCode(event.target.value)}
                            className="h-9 rounded-md border border-white/15 bg-surface px-2 text-foreground"
                        >
                            {codes.map((option) => (
                                <option key={option} value={option}>
                                    {option}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
            </div>

            <ul className="mt-4 divide-y divide-white/10 text-sm">
                {quote.lines.map((line) => (
                    <li
                        key={`${line.code}-${line.label}`}
                        className="flex justify-between gap-4 py-2.5"
                    >
                        <span className="text-white/80">
                            {line.label}
                            {line.quantity > 1 && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    × {line.quantity}
                                </span>
                            )}
                            {line.note && (
                                <span className="block text-xs text-muted-foreground">
                                    {line.note}
                                </span>
                            )}
                        </span>
                        <span className="text-right text-foreground tabular-nums">
                            {line.amount?.display ?? '—'}
                        </span>
                    </li>
                ))}
            </ul>

            <dl className="mt-2 grid gap-2 border-t border-white/15 pt-4 text-sm">
                <Row label="Total" value={shown?.total.display} strong />
                <Row
                    label="Pay now to reserve"
                    value={shown?.reservation.display}
                    accent
                />
                <Row
                    label="Balance at handover"
                    value={shown?.balance.display}
                />
                {shown?.deposit && (
                    <Row
                        label="Refundable security deposit"
                        value={shown.deposit.display}
                    />
                )}
            </dl>

            {code !== 'KES' && (
                <p className="mt-3 text-xs text-muted-foreground">
                    Charged in Kenyan shillings: {quote.total.display} in total.
                    Other currencies are a guide at today's rate.
                </p>
            )}
            {quote.requires_staff_quote && (
                <p className="mt-3 text-xs text-gold">
                    Delivery is quoted by our team and added to the balance at
                    handover.
                </p>
            )}
            <p className="mt-3 text-xs text-muted-foreground">
                {quote.mileage_km_per_day === null
                    ? 'Unlimited mileage.'
                    : `Includes ${quote.mileage_km_per_day} km a day.`}
            </p>
        </div>
    );
}

function Row({
    label,
    value,
    strong = false,
    accent = false,
}: {
    label: string;
    value: string | undefined;
    strong?: boolean;
    accent?: boolean;
}) {
    return (
        <div className="flex justify-between gap-4">
            <dt className={cn('text-white/80', strong && 'font-semibold')}>
                {label}
            </dt>
            <dd
                className={cn(
                    'text-right text-foreground tabular-nums',
                    strong && 'font-semibold',
                    accent && 'font-semibold text-gold',
                )}
            >
                {value ?? '—'}
            </dd>
        </div>
    );
}
