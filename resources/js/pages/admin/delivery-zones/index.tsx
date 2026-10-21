import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import DeliveryZoneController from '@/actions/App/Http/Controllers/Admin/DeliveryZoneController';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { SortableList } from '@/components/admin/sortable-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import type { AdminDeliveryZone } from '@/types';

export default function DeliveryZonesIndex({
    zones,
}: {
    zones: AdminDeliveryZone[];
}) {
    const can = useCan();
    const [items, setItems] = useState(zones);

    const row = (zone: AdminDeliveryZone) => (
        <div className="flex items-center gap-3">
            <div className="grid min-w-0 flex-1">
                <Link
                    href={DeliveryZoneController.edit(zone.id)}
                    className="truncate font-medium hover:underline"
                >
                    {zone.name}
                </Link>
                {zone.description && (
                    <span className="truncate text-xs text-muted-foreground">
                        {zone.description}
                    </span>
                )}
            </div>
            {zone.is_default && <Badge variant="secondary">Default</Badge>}
            {!zone.is_active && <Badge variant="outline">Off</Badge>}
            <span className="shrink-0 text-sm tabular-nums">
                {zone.fee?.display ?? 'Quoted by staff'}
            </span>
        </div>
    );

    return (
        <>
            <Head title="Delivery zones" />
            <AdminPage>
                <PageHeader
                    title="Delivery zones"
                    description="Where the car can be delivered and the fee. Zones without a fee are quoted by staff and left out of the booking total."
                    actions={
                        can('delivery-zones.create') && (
                            <Button asChild>
                                <Link href={DeliveryZoneController.create()}>
                                    <Plus /> New zone
                                </Link>
                            </Button>
                        )
                    }
                />
                <div className="max-w-3xl">
                    {items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No delivery zones yet.
                        </p>
                    ) : can('delivery-zones.update') ? (
                        <SortableList
                            items={items}
                            renderItem={row}
                            onReorder={(ids, reordered) => {
                                setItems(reordered);
                                router.put(
                                    DeliveryZoneController.reorder.url(),
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
                            {items.map((zone) => (
                                <li
                                    key={zone.id}
                                    className="rounded-md border bg-card p-2"
                                >
                                    {row(zone)}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </AdminPage>
        </>
    );
}

DeliveryZonesIndex.layout = {
    breadcrumbs: [
        { title: 'Delivery zones', href: DeliveryZoneController.index() },
    ],
};
