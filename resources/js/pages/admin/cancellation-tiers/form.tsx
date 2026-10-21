import { Head, Link, router, useForm } from '@inertiajs/react';
import CancellationTierController from '@/actions/App/Http/Controllers/Admin/CancellationTierController';
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
import type { AdminCancellationTier } from '@/types';

export default function CancellationTierForm({
    tier,
}: {
    tier: AdminCancellationTier | null;
}) {
    const can = useCan();
    const editable =
        tier === null
            ? can('cancellation-tiers.create')
            : can('cancellation-tiers.update');

    const form = useForm({
        hours_before_pickup: tier ? String(tier.hours_before_pickup) : '',
        refund_percent: tier ? String(tier.refund_percent_bp / 100) : '',
        label: tier?.label ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (tier === null) {
            form.post(CancellationTierController.store.url());
        } else {
            form.put(CancellationTierController.update.url(tier.id));
        }
    };

    return (
        <>
            <Head
                title={tier ? 'Cancellation tier' : 'New cancellation tier'}
            />
            <AdminPage>
                <PageHeader
                    title={tier ? 'Cancellation tier' : 'New cancellation tier'}
                    actions={
                        tier &&
                        can('cancellation-tiers.delete') && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        Delete
                                    </Button>
                                }
                                title="Delete this tier?"
                                confirmLabel="Delete tier"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        CancellationTierController.destroy.url(
                                            tier.id,
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
                            <CardTitle>Cancellation tier</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                name="hours_before_pickup"
                                label="Notice (hours before pickup)"
                                type="number"
                                min={0}
                                required
                                disabled={!editable}
                                value={form.data.hours_before_pickup}
                                error={form.errors.hours_before_pickup}
                                description="Applies when the renter cancels at least this early. 48 = two days."
                                onChange={(event) =>
                                    form.setData(
                                        'hours_before_pickup',
                                        event.target.value,
                                    )
                                }
                            />
                            <TextField
                                name="refund_percent"
                                label="Refund (% of the reservation)"
                                inputMode="decimal"
                                required
                                disabled={!editable}
                                value={form.data.refund_percent}
                                error={form.errors.refund_percent}
                                onChange={(event) =>
                                    form.setData(
                                        'refund_percent',
                                        event.target.value,
                                    )
                                }
                            />
                            <TextField
                                name="label"
                                label="Label"
                                className="sm:col-span-2"
                                disabled={!editable}
                                value={form.data.label}
                                error={form.errors.label}
                                description="Optional wording for the policy page, e.g. More than 7 days before pickup."
                                onChange={(event) =>
                                    form.setData('label', event.target.value)
                                }
                            />
                        </CardContent>
                        {editable && (
                            <CardFooter className="gap-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        (tier !== null && !form.isDirty)
                                    }
                                >
                                    {tier ? 'Save tier' : 'Create tier'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={CancellationTierController.index()}
                                    >
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

CancellationTierForm.layout = {
    breadcrumbs: [
        {
            title: 'Cancellation tiers',
            href: CancellationTierController.index(),
        },
    ],
};
