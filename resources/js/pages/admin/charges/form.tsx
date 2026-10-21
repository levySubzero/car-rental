import { Head, Link, router, useForm } from '@inertiajs/react';
import ChargeController from '@/actions/App/Http/Controllers/Admin/ChargeController';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import {
    MoneyField,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/admin/form-field';
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
import type { AdminCharge, Option } from '@/types';

/**
 * Create a charge (`charge` is null) or edit one.
 */
export default function ChargeForm({
    charge,
    basisOptions,
    payableAtOptions,
    appliesOptions,
    currencyOptions,
}: {
    charge: AdminCharge | null;
    basisOptions: Option[];
    payableAtOptions: Option[];
    appliesOptions: Option[];
    currencyOptions: Option[];
}) {
    const can = useCan();
    const editable =
        charge === null ? can('charges.create') : can('charges.update');

    const form = useForm({
        code: charge?.code ?? '',
        name: charge?.name ?? '',
        description: charge?.description ?? '',
        basis: charge?.basis ?? 'per_booking',
        amount: charge?.amount?.major ?? '',
        currency_code: charge?.currency_code ?? 'KES',
        tax_inclusive: charge?.tax_inclusive ?? true,
        payable_at: charge?.payable_at ?? 'reservation',
        applies: charge?.applies ?? 'extra',
        is_active: charge?.is_active ?? true,
    });

    const quotable =
        form.data.basis === 'per_day' || form.data.basis === 'per_booking';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (charge === null) {
            form.post(ChargeController.store.url());
        } else {
            form.put(ChargeController.update.url(charge.code));
        }
    };

    return (
        <>
            <Head title={charge?.name ?? 'New charge'} />
            <AdminPage>
                <PageHeader
                    title={charge?.name ?? 'New charge'}
                    description="Only per-day and per-booking charges paid with the booking or at handover count towards the total."
                    actions={
                        charge &&
                        can('charges.delete') && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        Delete
                                    </Button>
                                }
                                title={`Delete ${charge.name}?`}
                                description="Existing bookings keep the amount they were quoted. New quotes stop including it."
                                confirmLabel="Delete charge"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        ChargeController.destroy.url(
                                            charge.code,
                                        ),
                                    )
                                }
                            />
                        )
                    }
                />

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Charge</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
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
                                name="code"
                                label="Code"
                                required
                                disabled={!editable || charge !== null}
                                value={form.data.code}
                                error={form.errors.code}
                                description={
                                    charge === null
                                        ? 'e.g. child_seat. It cannot be changed later: bookings refer to it.'
                                        : undefined
                                }
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                            />
                            <TextareaField
                                name="description"
                                label="Description"
                                rows={2}
                                className="sm:col-span-2"
                                disabled={!editable}
                                value={form.data.description}
                                error={form.errors.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                            <SelectField
                                name="basis"
                                label="Applied"
                                options={basisOptions}
                                disabled={!editable}
                                value={form.data.basis}
                                error={form.errors.basis}
                                onChange={(event) =>
                                    form.setData(
                                        'basis',
                                        event.target
                                            .value as AdminCharge['basis'],
                                    )
                                }
                            />
                            <SelectField
                                name="payable_at"
                                label="Paid"
                                options={payableAtOptions}
                                disabled={!editable}
                                value={form.data.payable_at}
                                error={form.errors.payable_at}
                                onChange={(event) =>
                                    form.setData(
                                        'payable_at',
                                        event.target
                                            .value as AdminCharge['payable_at'],
                                    )
                                }
                            />
                            <SelectField
                                name="currency_code"
                                label="Currency"
                                options={currencyOptions}
                                disabled={!editable}
                                value={form.data.currency_code}
                                error={form.errors.currency_code}
                                onChange={(event) =>
                                    form.setData(
                                        'currency_code',
                                        event.target.value,
                                    )
                                }
                            />
                            <MoneyField
                                name="amount"
                                label="Amount"
                                currency={form.data.currency_code}
                                required={quotable}
                                disabled={!editable}
                                value={form.data.amount}
                                error={form.errors.amount}
                                description={
                                    quotable
                                        ? undefined
                                        : 'Optional. Leave empty to show "on request".'
                                }
                                onChange={(event) =>
                                    form.setData('amount', event.target.value)
                                }
                            />
                            <SelectField
                                name="applies"
                                label="Goes on a quote"
                                options={appliesOptions}
                                className="sm:col-span-2"
                                disabled={!editable}
                                value={form.data.applies}
                                error={form.errors.applies}
                                description={
                                    form.data.applies === 'extra'
                                        ? 'Offered on the booking form as an extra the renter can tick.'
                                        : undefined
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'applies',
                                        event.target
                                            .value as AdminCharge['applies'],
                                    )
                                }
                            />
                            <div className="grid gap-4 sm:col-span-2">
                                <ToggleRow
                                    id="tax_inclusive"
                                    label="Tax inclusive"
                                    checked={form.data.tax_inclusive}
                                    disabled={!editable}
                                    onChange={(value) =>
                                        form.setData('tax_inclusive', value)
                                    }
                                />
                                <ToggleRow
                                    id="is_active"
                                    label="Active"
                                    description="Inactive charges are left out of new quotes."
                                    checked={form.data.is_active}
                                    disabled={!editable}
                                    onChange={(value) =>
                                        form.setData('is_active', value)
                                    }
                                />
                            </div>
                        </CardContent>
                        {editable && (
                            <CardFooter className="gap-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        (charge !== null && !form.isDirty)
                                    }
                                >
                                    {charge ? 'Save charge' : 'Create charge'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={ChargeController.index()}>
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

ChargeForm.layout = {
    breadcrumbs: [{ title: 'Charges', href: ChargeController.index() }],
};
