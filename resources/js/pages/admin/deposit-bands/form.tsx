import { Head, Link, router, useForm } from '@inertiajs/react';
import DepositBandController from '@/actions/App/Http/Controllers/Admin/DepositBandController';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { TextField } from '@/components/admin/form-field';
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
import type { AdminDepositBand } from '@/types';

export default function DepositBandForm({
    band,
}: {
    band: AdminDepositBand | null;
}) {
    const can = useCan();
    const editable =
        band === null
            ? can('deposit-bands.create')
            : can('deposit-bands.update');

    const form = useForm({
        min_age: band ? String(band.min_age) : '',
        max_age: band ? String(band.max_age) : '',
        percent: band ? String(band.percent_bp / 100) : '',
        label: band?.label ?? '',
        is_active: band?.is_active ?? true,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (band === null) {
            form.post(DepositBandController.store.url());
        } else {
            form.put(DepositBandController.update.url(band.id));
        }
    };

    const title = band
        ? `Ages ${band.min_age}–${band.max_age}`
        : 'New deposit band';

    return (
        <>
            <Head title={title} />
            <AdminPage>
                <PageHeader
                    title={title}
                    actions={
                        band &&
                        can('deposit-bands.delete') && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        Delete
                                    </Button>
                                }
                                title="Delete this band?"
                                description="Drivers of these ages will not be able to book unless another band covers them."
                                confirmLabel="Delete band"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        DepositBandController.destroy.url(
                                            band.id,
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
                            <CardTitle>Deposit band</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-3">
                            <TextField
                                name="min_age"
                                label="From age"
                                type="number"
                                min={16}
                                max={99}
                                required
                                disabled={!editable}
                                value={form.data.min_age}
                                error={form.errors.min_age}
                                onChange={(event) =>
                                    form.setData('min_age', event.target.value)
                                }
                            />
                            <TextField
                                name="max_age"
                                label="To age (inclusive)"
                                type="number"
                                min={16}
                                max={99}
                                required
                                disabled={!editable}
                                value={form.data.max_age}
                                error={form.errors.max_age}
                                onChange={(event) =>
                                    form.setData('max_age', event.target.value)
                                }
                            />
                            <TextField
                                name="percent"
                                label="Deposit (% of car value)"
                                inputMode="decimal"
                                required
                                disabled={!editable}
                                value={form.data.percent}
                                error={form.errors.percent}
                                onChange={(event) =>
                                    form.setData('percent', event.target.value)
                                }
                            />
                            <TextField
                                name="label"
                                label="Label"
                                className="sm:col-span-3"
                                disabled={!editable}
                                value={form.data.label}
                                error={form.errors.label}
                                description="Optional, e.g. Young driver."
                                onChange={(event) =>
                                    form.setData('label', event.target.value)
                                }
                            />
                            <div className="sm:col-span-3">
                                <ToggleRow
                                    id="is_active"
                                    label="Active"
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
                                        (band !== null && !form.isDirty)
                                    }
                                >
                                    {band ? 'Save band' : 'Create band'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={DepositBandController.index()}>
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

DepositBandForm.layout = {
    breadcrumbs: [
        { title: 'Deposit bands', href: DepositBandController.index() },
    ],
};
