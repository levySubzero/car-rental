import { Head, Link, useForm } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { ContentField } from '@/components/admin/content/content-field';
import { TextareaField, TextField } from '@/components/admin/form-field';
import { AdminPage, PageHeader } from '@/components/admin/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import type {
    AdminContentPage,
    ContentFieldDefinition,
    ContentValue,
    MediaCollectionLimits,
    MediaItem,
} from '@/types';

/**
 * Edit one preset page (D22). The fields come from config/pages.php.
 */
export default function EditPage({
    page,
    imageLimits,
}: {
    page: AdminContentPage;
    imageLimits: MediaCollectionLimits;
}) {
    const can = useCan();
    const editable = can('pages.update');
    const [images, setImages] = useState<Record<string, MediaItem>>(
        page.images ?? {},
    );

    const form = useForm({
        content: page.content,
        meta_title: page.meta_title ?? '',
        meta_description: page.meta_description ?? '',
    });
    const errors = form.errors as Record<string, string | undefined>;

    const sections = groupBySection(page.fields ?? []);

    return (
        <>
            <Head title={page.title} />
            <AdminPage>
                <PageHeader title={page.title} description={page.description} />

                {page.missing.length > 0 && (
                    <Alert className="max-w-3xl">
                        <AlertDescription>
                            Still to write: {page.missing.join(', ')}. You can
                            save as you go.
                        </AlertDescription>
                    </Alert>
                )}

                {page.slug === 'home' && can('settings.home.update') && (
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        The hero, featured cars, section order and trust figures
                        are on the{' '}
                        <Link
                            href={SettingsController.edit('home')}
                            className="underline underline-offset-4"
                        >
                            Home page settings
                        </Link>
                        .
                    </p>
                )}

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(PageController.update.url(page.slug), {
                            preserveScroll: true,
                        });
                    }}
                    className="grid max-w-3xl gap-6"
                >
                    {sections.map(({ title, fields }, index) => (
                        <Card key={title ?? index}>
                            <CardHeader>
                                <CardTitle>{title ?? 'Content'}</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-5">
                                {fields.map((field) => (
                                    <Fragment key={field.key}>
                                        <ContentField
                                            field={field}
                                            value={form.data.content[field.key]}
                                            onChange={(value: ContentValue) =>
                                                form.setData('content', {
                                                    ...form.data.content,
                                                    [field.key]: value,
                                                })
                                            }
                                            errors={errors}
                                            images={images}
                                            onImage={(item) =>
                                                setImages((current) => ({
                                                    ...current,
                                                    [item.uuid]: item,
                                                }))
                                            }
                                            imageLimits={imageLimits}
                                            disabled={!editable}
                                        />
                                    </Fragment>
                                ))}
                            </CardContent>
                        </Card>
                    ))}

                    <Card>
                        <CardHeader>
                            <CardTitle>Search engines</CardTitle>
                            <CardDescription>
                                Optional. Leave blank to use the page heading.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <TextField
                                name="meta_title"
                                label="Title"
                                maxLength={70}
                                disabled={!editable}
                                value={form.data.meta_title}
                                error={errors.meta_title}
                                onChange={(event) =>
                                    form.setData(
                                        'meta_title',
                                        event.target.value,
                                    )
                                }
                            />
                            <TextareaField
                                name="meta_description"
                                label="Description"
                                rows={2}
                                maxLength={170}
                                disabled={!editable}
                                value={form.data.meta_description}
                                error={errors.meta_description}
                                onChange={(event) =>
                                    form.setData(
                                        'meta_description',
                                        event.target.value,
                                    )
                                }
                            />
                        </CardContent>
                        {editable && (
                            <CardFooter className="gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing || !form.isDirty}
                                >
                                    Save page
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={PageController.index()}>
                                        Back to pages
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

/**
 * A field with a `section` starts a new card; fields without one join the current card.
 */
function groupBySection(fields: ContentFieldDefinition[]) {
    const sections: {
        title: string | null;
        fields: ContentFieldDefinition[];
    }[] = [];

    for (const field of fields) {
        if (field.section || sections.length === 0) {
            sections.push({ title: field.section, fields: [] });
        }

        sections[sections.length - 1].fields.push(field);
    }

    return sections;
}

EditPage.layout = {
    breadcrumbs: [
        { title: 'Content', href: ContentController() },
        { title: 'Pages', href: PageController.index() },
    ],
};
