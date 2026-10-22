import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import CarTypeController from '@/actions/App/Http/Controllers/Admin/CarTypeController';
import { FlagSwitch } from '@/components/admin/catalog/flag-switch';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { SortableList } from '@/components/admin/sortable-list';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import type { AdminCarType } from '@/types';

export default function CarTypesIndex({ types }: { types: AdminCarType[] }) {
    const can = useCan();
    const [items, setItems] = useState(types);

    const row = (type: AdminCarType) => (
        <div className="flex items-center gap-3">
            <div className="grid min-w-0 flex-1">
                <Link
                    href={CarTypeController.edit(type.slug)}
                    className="truncate font-medium hover:underline"
                >
                    {type.name}
                </Link>
                {type.description && (
                    <span className="truncate text-xs text-muted-foreground">
                        {type.description}
                    </span>
                )}
            </div>
            <span className="shrink-0 text-sm text-muted-foreground">
                {type.cars_count ?? 0} car(s)
            </span>
            <span className="flex shrink-0 items-center gap-2 text-sm text-muted-foreground">
                <span className="hidden sm:inline">On website</span>
                <FlagSwitch
                    key={`${type.id}-${type.is_active}`}
                    url={CarTypeController.visibility.url(type.slug)}
                    checked={type.is_active}
                    label={`Show ${type.name} on the website`}
                    disabled={!can('car-types.update')}
                />
            </span>
        </div>
    );

    return (
        <>
            <Head title="Car types" />
            <AdminPage>
                <PageHeader
                    title="Car types"
                    description="Categories for the fleet filters and the home page rows. Drag to set their order. A type that is off the website keeps its cars in the fleet."
                    actions={
                        can('car-types.create') && (
                            <Button asChild>
                                <Link href={CarTypeController.create()}>
                                    <Plus /> New car type
                                </Link>
                            </Button>
                        )
                    }
                />
                <div className="max-w-3xl">
                    {items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No car types yet.
                        </p>
                    ) : can('car-types.update') ? (
                        <SortableList
                            items={items}
                            renderItem={row}
                            onReorder={(ids, reordered) => {
                                setItems(reordered);
                                router.put(
                                    CarTypeController.reorder.url(),
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
                            {items.map((type) => (
                                <li
                                    key={type.id}
                                    className="rounded-md border bg-card p-2"
                                >
                                    {row(type)}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </AdminPage>
        </>
    );
}

CarTypesIndex.layout = {
    breadcrumbs: [{ title: 'Car types', href: CarTypeController.index() }],
};
