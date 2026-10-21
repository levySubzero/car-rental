import { Head, Link, useForm } from '@inertiajs/react';
import CurrencyController from '@/actions/App/Http/Controllers/Admin/CurrencyController';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { SelectField, TextField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import type { AdminCurrency } from '@/types';

const decimalOptions = [
    { value: '0', label: 'None (KSh 15,000)' },
    { value: '2', label: 'Two ($ 116.28)' },
];

/**
 * Add a display currency (`currency` is null) or edit one. The code is fixed once created.
 */
export default function CurrencyForm({
    currency,
}: {
    currency: AdminCurrency | null;
}) {
    const can = useCan();
    const editable =
        currency === null ? can('currencies.create') : can('currencies.update');

    const form = useForm({
        code: currency?.code ?? '',
        name: currency?.name ?? '',
        symbol: currency?.symbol ?? '',
        decimals: String(currency?.decimals ?? 2),
        is_active: currency?.is_active ?? true,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (currency === null) {
            form.post(CurrencyController.store.url());
        } else {
            form.put(CurrencyController.update.url(currency.code));
        }
    };

    const title = currency
        ? `${currency.code} — ${currency.name}`
        : 'New currency';

    return (
        <>
            <Head title={title} />
            <AdminPage>
                <PageHeader
                    title={title}
                    description={
                        currency?.is_base
                            ? 'The base currency: prices are set and charged in it.'
                            : 'A currency renters can view prices in. It needs an exchange rate before prices show in it.'
                    }
                />

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Currency</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                name="code"
                                label="ISO code"
                                required
                                maxLength={3}
                                className="uppercase"
                                disabled={!editable || currency !== null}
                                value={form.data.code}
                                error={form.errors.code}
                                description={
                                    currency === null
                                        ? 'Three letters, e.g. AUD. It cannot be changed later.'
                                        : undefined
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                            />
                            <TextField
                                name="name"
                                label="Name"
                                required
                                disabled={!editable}
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <TextField
                                name="symbol"
                                label="Symbol"
                                required
                                maxLength={8}
                                disabled={!editable}
                                value={form.data.symbol}
                                error={form.errors.symbol}
                                onChange={(event) =>
                                    form.setData('symbol', event.target.value)
                                }
                            />
                            <SelectField
                                name="decimals"
                                label="Decimals shown"
                                options={decimalOptions}
                                disabled={!editable}
                                value={form.data.decimals}
                                error={form.errors.decimals}
                                onChange={(event) =>
                                    form.setData('decimals', event.target.value)
                                }
                            />
                            {!currency?.is_base && (
                                <div className="sm:col-span-2">
                                    <ToggleRow
                                        id="is_active"
                                        label="Active"
                                        description="Inactive currencies are left out of the currency switcher."
                                        checked={form.data.is_active}
                                        disabled={!editable}
                                        onChange={(value) =>
                                            form.setData('is_active', value)
                                        }
                                    />
                                </div>
                            )}
                        </CardContent>
                        {editable && (
                            <CardFooter className="gap-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        (currency !== null && !form.isDirty)
                                    }
                                >
                                    {currency
                                        ? 'Save currency'
                                        : 'Add currency'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={CurrencyController.index()}>
                                        Cancel
                                    </Link>
                                </Button>
                            </CardFooter>
                        )}
                    </Card>
                </form>
            </AdminPage>
        </>
    );
}

CurrencyForm.layout = {
    breadcrumbs: [{ title: 'Currencies', href: CurrencyController.index() }],
};
