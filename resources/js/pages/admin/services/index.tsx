import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import { FlagSwitch } from '@/components/admin/catalog/flag-switch';
import { MediaThumb } from '@/components/admin/catalog/media-thumb';
import { ContentTabs } from '@/components/admin/content/content-tabs';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { SortableList } from '@/components/admin/sortable-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import type { AdminService } from '@/types';

export default function ServicesIndex({
    services,
}: {
    services: AdminService[];
}) {
    const can = useCan();
    const [items, setItems] = useState(services);
    const editable = can('services.update');

    const row = (service: AdminService) => (
        <div className="flex items-center gap-3">
            <MediaThumb media={service.image} className="size-10" />
            <div className="grid min-w-0 flex-1">
                <Link
                    href={ServiceController.edit(service.slug)}
                    className="truncate text-sm font-medium hover:underline"
                >
                    {service.title}
                </Link>
                <span className="truncate text-xs text-muted-foreground">
                    {service.summary}
                </span>
            </div>
            <Badge variant="outline" className="hidden sm:inline-flex">
                {service.cta_label}
            </Badge>
            <FlagSwitch
                key={`${service.id}-${service.is_active}`}
                url={ServiceController.active.url(service.slug)}
                checked={service.is_active}
                label={`Show ${service.title} on the site`}
                disabled={!editable}
            />
        </div>
    );

    return (
        <>
            <Head title="Services" />
            <AdminPage>
                <PageHeader
                    title="Content"
                    description="What Baines offers besides a plain rental. Drag to set the order on the home and services pages; switch a service off to hide it."
                    actions={
                        can('services.create') && (
                            <Button asChild>
                                <Link href={ServiceController.create()}>
                                    <Plus /> New service
                                </Link>
                            </Button>
                        )
                    }
                />
                <ContentTabs />
                <div className="max-w-3xl">
                    {items.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No services yet.
                        </p>
                    ) : editable ? (
                        <SortableList
                            items={items}
                            renderItem={row}
                            onReorder={(ids, reordered) => {
                                setItems(reordered);
                                router.put(
                                    ServiceController.reorder.url(),
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
                            {items.map((service) => (
                                <li
                                    key={service.id}
                                    className="rounded-md border p-2"
                                >
                                    {row(service)}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </AdminPage>
        </>
    );
}

ServicesIndex.layout = {
    breadcrumbs: [
        { title: 'Content', href: ContentController() },
        { title: 'Services', href: ServiceController.index() },
    ],
};
