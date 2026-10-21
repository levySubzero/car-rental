import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import ChargeController from '@/actions/App/Http/Controllers/Admin/ChargeController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { SortableList } from '@/components/admin/sortable-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import type { AdminCharge } from '@/types';

export default function ChargesIndex({ charges }: { charges: AdminCharge[] }) {
    const can = useCan();
    const [items, setItems] = useState(charges);

    const row = (charge: AdminCharge) => (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
            <div className="grid min-w-0 flex-1">
                <Link
                    href={ChargeController.edit(charge.code)}
                    className="truncate font-medium hover:underline"
                >
                    {charge.name}
                </Link>
                <span className="truncate text-xs text-muted-foreground">
                    {charge.basis_label} · {charge.payable_at_label} ·{' '}
                    {charge.applies_label}
                </span>
            </div>
            <span className="shrink-0 text-sm tabular-nums">
                {charge.amount?.display ?? 'On request'}
            </span>
            {!charge.is_active && <Badge variant="outline">Off</Badge>}
        </div>
    );

    return (
        <>
            <Head title="Charges" />
            <AdminPage>
                <PageHeader
                    title="Charges"
                    description="Extras on top of the daily rate. Foreign-currency amounts are converted at the market rate when quoted. Drag to set the order on quotes."
                    actions={
                        can('charges.create') && (
                            <Button asChild>
                                <Link href={ChargeController.create()}>
                                    <Plus /> New charge
                                </Link>
                            </Button>
                        )
                    }
                />
                <div className="max-w-3xl">
                    {items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No charges yet.
                        </p>
                    ) : can('charges.update') ? (
                        <SortableList
                            items={items}
                            renderItem={row}
                            onReorder={(ids, reordered) => {
                                setItems(reordered);
                                router.put(
                                    ChargeController.reorder.url(),
                                    { ids },
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                );
                            }}
                        />
                    ) : (
                        <ul className="space-y-2">
                            {items.map((charge) => (
                                <li
                                    key={charge.id}
                                    className="rounded-md border bg-card p-2"
                                >
                                    {row(charge)}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </AdminPage>
        </>
    );
}

ChargesIndex.layout = {
    breadcrumbs: [{ title: 'Charges', href: ChargeController.index() }],
};
