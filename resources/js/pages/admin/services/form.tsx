import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import ServiceController from '@/actions/App/Http/Controllers/Admin/ServiceController';
import { SlugField } from '@/components/admin/catalog/slug-field';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import {
    Field,
    SelectField,
    TextareaField,
    TextField,
} from '@/components/admin/form-field';
import { MarkdownField } from '@/components/admin/markdown-field';
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
import type {
    AdminService,
    MediaCollectionLimits,
    MediaItem,
    Option,
} from '@/types';

/**
 * Create a service (`service` is null) or edit one.
 */
export default function ServiceForm({
    service,
    imageLimits,
    ctaOptions,
}: {
    service: AdminService | null;
    imageLimits: MediaCollectionLimits;
    ctaOptions: Option[];
}) {
    const can = useCan();
    const editable =
        service === null ? can('services.create') : can('services.update');
    const [image, setImage] = useState<MediaItem[]>(
        service?.image ? [service.image] : [],
    );

    const form = useForm({
        title: service?.title ?? '',
        slug: '',
        summary: service?.summary ?? '',
        body: service?.body ?? '',
        cta_type: service?.cta_type ?? 'book',
        cta_value: service?.cta_value ?? '',
        is_active: service?.is_active ?? true,
        image: service?.image?.uuid ?? (null as string | null),
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (service === null) {
            form.post(ServiceController.store.url());
        } else {
            form.put(ServiceController.update.url(service.slug), {
                preserveScroll: true,
                onSuccess: () => form.setData('slug', ''),
            });
        }
    };

    return (
        <>
            <Head title={service?.title ?? 'New service'} />
            <AdminPage>
                <PageHeader
                    title={service?.title ?? 'New service'}
                    description="Shown on the home page and the services page."
                    actions={
                        service &&
                        can('services.delete') && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="destructive">
                                        Delete
                                    </Button>
                                }
                                title={`Delete ${service.title}?`}
                                description="This cannot be undone. Switch it off instead to hide it for now."
                                confirmLabel="Delete service"
                                destructive
                                onConfirm={() =>
                                    router.delete(
                                        ServiceController.destroy.url(
                                            service.slug,
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
                            <CardTitle>Service</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <TextField
                                name="title"
                                label="Title"
                                required
                                disabled={!editable}
                                value={form.data.title}
                                error={form.errors.title}
                                onChange={(event) =>
                                    form.setData('title', event.target.value)
                                }
                            />
                            <SlugField
                                value={form.data.slug}
                                onChange={(slug) => form.setData('slug', slug)}
                                name={form.data.title}
                                current={service?.slug}
                                prefix="/services#"
                                error={form.errors.slug}
                                disabled={!editable}
                            />
                            <TextareaField
                                name="summary"
                                label="Summary"
                                description="One or two sentences for the service card."
                                rows={2}
                                maxLength={300}
                                required
                                disabled={!editable}
                                value={form.data.summary}
                                error={form.errors.summary}
                                onChange={(event) =>
                                    form.setData('summary', event.target.value)
                                }
                            />
                            <MarkdownField
                                name="body"
                                label="Details"
                                description="Optional. Shown on the services page."
                                rows={8}
                                disabled={!editable}
                                value={form.data.body}
                                error={form.errors.body}
                                onChange={(body) => form.setData('body', body)}
                            />
                            <Field label="Image" error={form.errors.image}>
                                <MediaUploader
                                    limits={imageLimits}
                                    value={image}
                                    disabled={!editable}
                                    onChange={(items) => {
                                        setImage(items);
                                        form.setData(
                                            'image',
                                            items[0]?.uuid ?? null,
                                        );
                                    }}
                                />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Button</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <SelectField
                                name="cta_type"
                                label="Button action"
                                options={ctaOptions}
                                disabled={!editable}
                                value={form.data.cta_type}
                                error={form.errors.cta_type}
                                onChange={(event) =>
                                    form.setData(
                                        'cta_type',
                                        event.target
                                            .value as AdminService['cta_type'],
                                    )
                                }
                            />
                            {form.data.cta_type === 'link' && (
                                <TextField
                                    name="cta_value"
                                    label="Link URL"
                                    type="url"
                                    placeholder="https://"
                                    required
                                    disabled={!editable}
                                    value={form.data.cta_value}
                                    error={form.errors.cta_value}
                                    onChange={(event) =>
                                        form.setData(
                                            'cta_value',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                            {form.data.cta_type === 'whatsapp' && (
                                <TextField
                                    name="cta_value"
                                    label="WhatsApp message"
                                    description="Optional. Leave blank to use the general enquiry message."
                                    disabled={!editable}
                                    value={form.data.cta_value}
                                    error={form.errors.cta_value}
                                    onChange={(event) =>
                                        form.setData(
                                            'cta_value',
                                            event.target.value,
                                        )
                                    }
                                />
                            )}
                            <ToggleRow
                                id="is_active"
                                label="Show on the site"
                                description="Hidden services stay here but are not shown to visitors."
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
                                        (service !== null && !form.isDirty)
                                    }
                                >
                                    {service
                                        ? 'Save service'
                                        : 'Create service'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={ServiceController.index()}>
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

ServiceForm.layout = {
    breadcrumbs: [
        { title: 'Content', href: ContentController() },
        { title: 'Services', href: ServiceController.index() },
    ],
};
