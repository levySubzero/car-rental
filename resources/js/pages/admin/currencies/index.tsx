import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import CurrencyController from '@/actions/App/Http/Controllers/Admin/CurrencyController';
import { FlagSwitch } from '@/components/admin/catalog/flag-switch';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { SortableList } from '@/components/admin/sortable-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import type { AdminCurrency } from '@/types';

export default function CurrenciesIndex({
    currencies,
}: {
    currencies: AdminCurrency[];
}) {
    const can = useCan();
    const editable = can('currencies.update');
    const [items, setItems] = useState(currencies);

    const row = (currency: AdminCurrency) => (
        <div className="flex items-center gap-3">
            <span className="w-12 shrink-0 font-mono text-sm font-medium">
                {currency.code}
            </span>
            <Link
                href={CurrencyController.edit(currency.code)}
                className="min-w-0 flex-1 truncate text-sm hover:underline"
            >
                {currency.name}{' '}
                <span className="text-muted-foreground">
                    ({currency.symbol})
                </span>
            </Link>
            {currency.is_base ? (
                <Badge variant="secondary">Base</Badge>
            ) : (
                <FlagSwitch
                    key={`${currency.id}-${currency.is_active}`}
                    url={CurrencyController.active.url(currency.code)}
                    checked={currency.is_active}
                    label={`Show prices in ${currency.code}`}
                    disabled={!editable}
                />
            )}
        </div>
    );

    return (
        <>
            <Head title="Currencies" />
            <AdminPage>
                <PageHeader
                    title="Currencies"
                    description="Renters can view prices in the active currencies; they always pay in the base currency. Drag to set the order of the currency switcher."
                    actions={
                        can('currencies.create') && (
                            <Button asChild>
                                <Link href={CurrencyController.create()}>
                                    <Plus /> New currency
                                </Link>
                            </Button>
                        )
                    }
                />
                <div className="max-w-2xl">
                    {editable ? (
                        <SortableList
                            items={items}
                            renderItem={row}
                            onReorder={(ids, reordered) => {
                                setItems(reordered);
                                router.put(
                                    CurrencyController.reorder.url(),
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
                            {items.map((currency) => (
                                <li
                                    key={currency.id}
                                    className="rounded-md border bg-card p-2"
                                >
                                    {row(currency)}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </AdminPage>
        </>
    );
}

CurrenciesIndex.layout = {
    breadcrumbs: [{ title: 'Currencies', href: CurrencyController.index() }],
};
