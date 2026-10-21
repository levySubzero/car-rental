import { Head, Link, router, useForm } from '@inertiajs/react';
import DeliveryZoneController from '@/actions/App/Http/Controllers/Admin/DeliveryZoneController';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import {
    MoneyField,
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
import type { AdminDeliveryZone } from '@/types';

export default function DeliveryZoneForm({
    zone,
}: {
    zone: AdminDeliveryZone | null;
}) {
    const can = useCan();
    const editable =
        zone === null
            ? can('delivery-zones.create')
            : can('delivery-zones.update');

    const form = useForm({
        name: zone?.name ?? '',
        description: zone?.description ?? '',
        fee: zone?.fee?.major ?? '',
        is_default: zone?.is_default ?? false,
        is_active: zone?.is_active ?? true,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (zone === null) {
            form.post(DeliveryZoneController.store.url());
        } else {
            form.put(DeliveryZoneController.update.url(zone.id));
        }
    };

    return (
        <>
            <Head title={zone?.name ?? 'New delivery zone'} />
            <AdminPage>
                <PageHeader
                    title={zone?.name ?? 'New delivery zone'}
                    actions={
                        zone &&
                        can('delivery-zones.delete') && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        Delete
                                    </Button>
                                }
                                title={`Delete ${zone.name}?`}
                                description="Existing bookings keep the fee they were quoted."
                                confirmLabel="Delete zone"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        DeliveryZoneController.destroy.url(
                                            zone.id,
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
                            <CardTitle>Delivery zone</CardTitle>
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
                            <MoneyField
                                name="fee"
                                label="Fee"
                                disabled={!editable}
                                value={form.data.fee}
                                error={form.errors.fee}
                                description="Leave empty if staff quote it per booking."
                                onChange={(event) =>
                                    form.setData('fee', event.target.value)
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
                            <div className="grid gap-4 sm:col-span-2">
                                <ToggleRow
                                    id="is_default"
                                    label="Default zone"
                                    description="Preselected on the booking form. Only one zone can be the default."
                                    checked={form.data.is_default}
                                    disabled={!editable}
                                    onChange={(value) =>
                                        form.setData('is_default', value)
                                    }
                                />
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
                                        (zone !== null && !form.isDirty)
                                    }
                                >
                                    {zone ? 'Save zone' : 'Create zone'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={DeliveryZoneController.index()}>
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

DeliveryZoneForm.layout = {
    breadcrumbs: [
        { title: 'Delivery zones', href: DeliveryZoneController.index() },
    ],
};
