import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import CancellationTierController from '@/actions/App/Http/Controllers/Admin/CancellationTierController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { percentFromBp } from '@/lib/format';
import type { AdminCancellationTier } from '@/types';

export function noticeLabel(hours: number): string {
    if (hours === 0) {
        return 'Any time before pickup';
    }

    return hours % 24 === 0
        ? `${hours / 24} day(s) or more before pickup`
        : `${hours} hour(s) or more before pickup`;
}

export default function CancellationTiersIndex({
    tiers,
}: {
    tiers: AdminCancellationTier[];
}) {
    const can = useCan();

    return (
        <>
            <Head title="Cancellation tiers" />
            <AdminPage>
                <PageHeader
                    title="Cancellation tiers"
                    description="How much of the reservation is refunded, by how early the renter cancels. Shown on the rental policy page."
                    actions={
                        can('cancellation-tiers.create') && (
                            <Button asChild>
                                <Link
                                    href={CancellationTierController.create()}
                                >
                                    <Plus /> New tier
                                </Link>
                            </Button>
                        )
                    }
                />
                <div className="max-w-3xl">
                    {tiers.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No tiers yet. Until there are, cancellations are
                            handled case by case.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {tiers.map((tier) => (
                                <li
                                    key={tier.id}
                                    className="flex items-center gap-3 p-3"
                                >
                                    <Link
                                        href={CancellationTierController.edit(
                                            tier.id,
                                        )}
                                        className="min-w-0 flex-1 font-medium hover:underline"
                                    >
                                        {tier.label ??
                                            noticeLabel(
                                                tier.hours_before_pickup,
                                            )}
                                    </Link>
                                    <span className="text-sm tabular-nums">
                                        {percentFromBp(tier.refund_percent_bp)}{' '}
                                        refund
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </AdminPage>
        </>
    );
}

CancellationTiersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Cancellation tiers',
            href: CancellationTierController.index(),
        },
    ],
};
