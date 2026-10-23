import { percentFromBp } from '@/lib/format';
import type { CancellationTier } from '@/types';

/**
 * Refunds on cancellation (D34): a share of the reservation, by notice given.
 */
export function CancellationTerms({ tiers }: { tiers: CancellationTier[] }) {
    if (tiers.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Cancellations are handled as set out in our rental policy.
            </p>
        );
    }

    return (
        <ul className="grid gap-1.5 text-sm text-white/80">
            {tiers.map((tier) => (
                <li key={tier.hours_before_pickup}>
                    {tier.label ??
                        `Cancel ${notice(tier.hours_before_pickup)} or more before pickup`}
                    : {percentFromBp(tier.refund_percent_bp)} of the reservation
                    back
                </li>
            ))}
            <li className="text-muted-foreground">
                Less notice, or not collecting the car: no refund.
            </li>
        </ul>
    );
}

function notice(hours: number): string {
    return hours % 24 === 0 && hours >= 24
        ? `${hours / 24} ${hours === 24 ? 'day' : 'days'}`
        : `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
}
