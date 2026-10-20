import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import PaymentMethodController from '@/actions/App/Http/Controllers/Admin/PaymentMethodController';
import { FlagSwitch } from '@/components/admin/catalog/flag-switch';
import { ContentTabs } from '@/components/admin/content/content-tabs';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { SortableList } from '@/components/admin/sortable-list';
import { Badge } from '@/components/ui/badge';
import { useCan } from '@/hooks/use-can';
import type { AdminPaymentMethod } from '@/types';

export default function PaymentMethodsIndex({
    methods,
}: {
    methods: AdminPaymentMethod[];
}) {
    const can = useCan();
    const editable = can('payment-methods.update');
    const [items, setItems] = useState(methods);

    const row = (method: AdminPaymentMethod) => (
        <div className="flex items-center gap-3">
            <Link
                href={PaymentMethodController.edit(method.code)}
                className="min-w-0 flex-1 truncate text-sm font-medium hover:underline"
            >
                {method.name}
            </Link>
            {method.has_todo && (
                <Badge variant="destructive">Details missing</Badge>
            )}
            <FlagSwitch
                key={`${method.id}-${method.is_active}`}
                url={PaymentMethodController.active.url(method.code)}
                checked={method.is_active}
                label={`Offer ${method.name} to renters`}
                disabled={!editable}
            />
        </div>
    );

    return (
        <>
            <Head title="Payment methods" />
            <AdminPage>
                <PageHeader
                    title="Content"
                    description="How renters pay the reservation. Drag to set the order renters see; switch a method off to stop offering it."
                />
                <ContentTabs />
                <div className="max-w-2xl">
                    {editable ? (
                        <SortableList
                            items={items}
                            renderItem={row}
                            onReorder={(ids, reordered) => {
                                setItems(reordered);
                                router.put(
                                    PaymentMethodController.reorder.url(),
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
                            {items.map((method) => (
                                <li
                                    key={method.id}
                                    className="rounded-md border p-2"
                                >
                                    {row(method)}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </AdminPage>
        </>
    );
}

PaymentMethodsIndex.layout = {
    breadcrumbs: [
        { title: 'Content', href: ContentController() },
        { title: 'Payment methods', href: PaymentMethodController.index() },
    ],
};
