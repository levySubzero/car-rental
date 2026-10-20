import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import BrandController from '@/actions/App/Http/Controllers/Admin/BrandController';
import { SlugField } from '@/components/admin/catalog/slug-field';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { Field, TextField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { MediaUploader } from '@/components/media/media-uploader';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import type { AdminBrand, MediaCollectionLimits, MediaItem } from '@/types';

/**
 * Create a brand (`brand` is null) or edit one.
 */
export default function BrandForm({
    brand,
    logoLimits,
}: {
    brand: AdminBrand | null;
    logoLimits: MediaCollectionLimits;
}) {
    const can = useCan();
    const editable =
        brand === null ? can('brands.create') : can('brands.update');
    const [logo, setLogo] = useState<MediaItem[]>(
        brand?.logo ? [brand.logo] : [],
    );

    const form = useForm({
        name: brand?.name ?? '',
        slug: '',
        show_in_marquee: brand?.show_in_marquee ?? true,
        is_active: brand?.is_active ?? true,
        logo: brand?.logo?.uuid ?? (null as string | null),
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (brand === null) {
            form.post(BrandController.store.url());
        } else {
            form.put(BrandController.update.url(brand.slug), {
                preserveScroll: true,
                onSuccess: () => form.setData('slug', ''),
            });
        }
    };

    return (
        <>
            <Head title={brand?.name ?? 'New brand'} />
            <AdminPage>
                <PageHeader
                    title={brand?.name ?? 'New brand'}
                    description={
                        brand
                            ? `${brand.cars_count ?? 0} car(s) use this brand.`
                            : 'Add a car make and its logo.'
                    }
                    actions={
                        brand &&
                        can('brands.delete') && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        Delete
                                    </Button>
                                }
                                title={`Delete ${brand.name}?`}
                                description={
                                    brand.cars_count
                                        ? 'Move its cars to another brand first.'
                                        : 'This cannot be undone.'
                                }
                                confirmLabel="Delete brand"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        BrandController.destroy.url(brand.slug),
                                    )
                                }
                            />
                        )
                    }
                />

                <form onSubmit={submit} className="grid max-w-3xl gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Brand</CardTitle>
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
                                current={brand?.slug}
                                prefix="/car-brands/"
                                error={form.errors.slug}
                                disabled={!editable}
                            />
                            <Field label="Logo" error={form.errors.logo}>
                                <MediaUploader
                                    limits={logoLimits}
                                    value={logo}
                                    disabled={!editable}
                                    onChange={(items) => {
                                        setLogo(items);
                                        form.setData(
                                            'logo',
                                            items[0]?.uuid ?? null,
                                        );
                                    }}
                                />
                            </Field>
                            <ToggleRow
                                id="is_active"
                                label="Active"
                                description="Hidden brands are left out of filters, pages and the marquee."
                                checked={form.data.is_active}
                                disabled={!editable}
                                onChange={(value) =>
                                    form.setData('is_active', value)
                                }
                            />
                            <ToggleRow
                                id="show_in_marquee"
                                label="Show in the marquee"
                                description="The scrolling row of logos on the home page."
                                checked={form.data.show_in_marquee}
                                disabled={!editable}
                                onChange={(value) =>
                                    form.setData('show_in_marquee', value)
                                }
                            />
                        </CardContent>
                        {editable && (
                            <CardFooter className="gap-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        (brand !== null && !form.isDirty)
                                    }
                                >
                                    {brand ? 'Save brand' : 'Create brand'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={BrandController.index()}>
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

BrandForm.layout = {
    breadcrumbs: [{ title: 'Brands', href: BrandController.index() }],
};
