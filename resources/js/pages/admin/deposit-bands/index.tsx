import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import DepositBandController from '@/actions/App/Http/Controllers/Admin/DepositBandController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { percentFromBp } from '@/lib/format';
import type { AdminDepositBand } from '@/types';

export default function DepositBandsIndex({
    bands,
}: {
    bands: AdminDepositBand[];
}) {
    const can = useCan();

    return (
        <>
            <Head title="Deposit bands" />
            <AdminPage>
                <PageHeader
                    title="Deposit bands"
                    description="The refundable security deposit, as a share of the car value, by the driver's age at pickup. A driver whose age is in no active band cannot book."
                    actions={
                        can('deposit-bands.create') && (
                            <Button asChild>
                                <Link href={DepositBandController.create()}>
                                    <Plus /> New band
                                </Link>
                            </Button>
                        )
                    }
                />
                <div className="max-w-3xl">
                    {bands.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No deposit bands yet: no one can book.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {bands.map((band) => (
                                <li
                                    key={band.id}
                                    className="flex items-center gap-3 p-3"
                                >
                                    <Link
                                        href={DepositBandController.edit(
                                            band.id,
                                        )}
                                        className="min-w-0 flex-1 font-medium hover:underline"
                                    >
                                        {band.min_age === band.max_age
                                            ? `Age ${band.min_age}`
                                            : `Ages ${band.min_age}–${band.max_age}`}
                                        {band.label && (
                                            <span className="font-normal text-muted-foreground">
                                                {' '}
                                                · {band.label}
                                            </span>
                                        )}
                                    </Link>
                                    {!band.is_active && (
                                        <Badge variant="outline">Off</Badge>
                                    )}
                                    <span className="text-sm tabular-nums">
                                        {percentFromBp(band.percent_bp)} of car
                                        value
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

DepositBandsIndex.layout = {
    breadcrumbs: [
        { title: 'Deposit bands', href: DepositBandController.index() },
    ],
};
