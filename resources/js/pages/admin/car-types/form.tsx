import { Head, Link, router, useForm } from '@inertiajs/react';
import CarTypeController from '@/actions/App/Http/Controllers/Admin/CarTypeController';
import { SlugField } from '@/components/admin/catalog/slug-field';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { TextareaField, TextField } from '@/components/admin/form-field';
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
import type { AdminCarType } from '@/types';

/**
 * Create a car type (`type` is null) or edit one.
 */
export default function CarTypeForm({ type }: { type: AdminCarType | null }) {
    const can = useCan();
    const editable =
        type === null ? can('car-types.create') : can('car-types.update');

    const form = useForm({
        name: type?.name ?? '',
        slug: '',
        description: type?.description ?? '',
        is_active: type?.is_active ?? true,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (type === null) {
            form.post(CarTypeController.store.url());
        } else {
            form.put(CarTypeController.update.url(type.slug));
        }
    };

    return (
        <>
            <Head title={type?.name ?? 'New car type'} />
            <AdminPage>
                <PageHeader
                    title={type?.name ?? 'New car type'}
                    description={
                        type
                            ? `${type.cars_count ?? 0} car(s) are this type.`
                            : 'Add a category such as SUV or Executive.'
                    }
                    actions={
                        type &&
                        can('car-types.delete') && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        Delete
                                    </Button>
                                }
                                title={`Delete ${type.name}?`}
                                description={
                                    type.cars_count
                                        ? 'Move its cars to another type first.'
                                        : 'This cannot be undone.'
                                }
                                confirmLabel="Delete car type"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        CarTypeController.destroy.url(
                                            type.slug,
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
                            <CardTitle>Car type</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
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
                            <SlugField
                                value={form.data.slug}
                                onChange={(slug) => form.setData('slug', slug)}
                                name={form.data.name}
                                current={type?.slug}
                                prefix="/car-types/"
                                error={form.errors.slug}
                                disabled={!editable}
                            />
                            <TextareaField
                                name="description"
                                label="Description"
                                rows={3}
                                disabled={!editable}
                                value={form.data.description}
                                error={form.errors.description}
                                description="A line shown with this type on the site."
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                            <ToggleRow
                                id="is_active"
                                label="Active"
                                description="Hidden types are left out of the filters and the home page."
                                checked={form.data.is_active}
                                disabled={!editable}
                                onChange={(value) =>
                                    form.setData('is_active', value)
                                }
                            />
                        </CardContent>
                        {editable && (
                            <CardFooter className="gap-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        (type !== null && !form.isDirty)
                                    }
                                >
                                    {type ? 'Save car type' : 'Create car type'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={CarTypeController.index()}>
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

CarTypeForm.layout = {
    breadcrumbs: [{ title: 'Car types', href: CarTypeController.index() }],
};
