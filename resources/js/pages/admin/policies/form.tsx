import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import PolicyController from '@/actions/App/Http/Controllers/Admin/PolicyController';
import { SlugField } from '@/components/admin/catalog/slug-field';
import { ToggleRow } from '@/components/admin/catalog/toggle-row';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { LineDiff } from '@/components/admin/content/line-diff';
import { PolicyStatus } from '@/components/admin/content/policy-status';
import {
    SelectField,
    TextareaField,
    TextField,
} from '@/components/admin/form-field';
import { MarkdownField } from '@/components/admin/markdown-field';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCan } from '@/hooks/use-can';
import { formatDateTime } from '@/lib/format';
import type { AdminPolicy, AdminPolicyVersion, Option } from '@/types';

/**
 * Create a policy (`policy` is null), or edit one: its text as a draft, its details, and
 * the history of published versions.
 */
export default function PolicyForm({
    policy,
    groupOptions,
}: {
    policy: AdminPolicy | null;
    groupOptions: Option[];
}) {
    if (policy === null) {
        return <CreatePolicy groupOptions={groupOptions} />;
    }

    return (
        <EditPolicy
            key={policy.updated_at}
            policy={policy}
            groupOptions={groupOptions}
        />
    );
}

function CreatePolicy({ groupOptions }: { groupOptions: Option[] }) {
    const form = useForm({
        title: '',
        slug: '',
        group: groupOptions[0]?.value ?? '',
        summary: '',
        show_on_home: false,
        body: '',
    });

    return (
        <>
            <Head title="New policy" />
            <AdminPage>
                <PageHeader
                    title="New policy"
                    description="It is saved as a draft. Renters see it once it is published."
                />
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(PolicyController.store.url());
                    }}
                    className="grid max-w-3xl gap-6"
                >
                    <Card>
                        <CardContent className="grid gap-4 pt-6">
                            <DetailsFields
                                data={form.data}
                                errors={form.errors}
                                set={(patch) =>
                                    form.setData({ ...form.data, ...patch })
                                }
                                groupOptions={groupOptions}
                                editable
                            />
                            <MarkdownField
                                name="body"
                                label="Text"
                                rows={14}
                                value={form.data.body}
                                error={form.errors.body}
                                onChange={(body) => form.setData('body', body)}
                            />
                        </CardContent>
                        <CardFooter className="gap-2">
                            <Button type="submit" disabled={form.processing}>
                                Create draft
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={PolicyController.index()}>
                                    Cancel
                                </Link>
                            </Button>
                        </CardFooter>
                    </Card>
                </form>
            </AdminPage>
        </>
    );
}

function EditPolicy({
    policy,
    groupOptions,
}: {
    policy: AdminPolicy;
    groupOptions: Option[];
}) {
    const can = useCan();
    const editable = can('policies.update');
    const live = policy.published?.body ?? '';

    const draft = useForm({ body: policy.draft?.body ?? live });
    const details = useForm({
        title: policy.title,
        slug: '',
        group: policy.group,
        summary: policy.summary ?? '',
        show_on_home: policy.show_on_home,
    });

    const unsaved = draft.isDirty;
    const publishBlocked = policy.draft?.has_confirm
        ? 'Remove the [CONFIRM] note before publishing.'
        : unsaved
          ? 'Save the draft before publishing.'
          : null;

    return (
        <>
            <Head title={policy.title} />
            <AdminPage>
                <PageHeader
                    title={policy.title}
                    description={<PolicyStatus policy={policy} />}
                    actions={
                        <div className="flex gap-2">
                            {policy.draft && can('policies.publish') && (
                                <ConfirmDialog
                                    trigger={
                                        <Button
                                            disabled={publishBlocked !== null}
                                        >
                                            Publish v{policy.draft.version}
                                        </Button>
                                    }
                                    title={`Publish version ${policy.draft.version}?`}
                                    description="New bookings will accept this text from now on. Bookings already made keep the text they accepted."
                                    confirmLabel="Publish"
                                    onConfirm={() =>
                                        router.post(
                                            PolicyController.publish.url(
                                                policy.slug,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            )}
                            {can('policies.delete') && (
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="destructive">
                                            Delete
                                        </Button>
                                    }
                                    title={`Delete ${policy.title}?`}
                                    description={
                                        policy.published
                                            ? 'It disappears from the site, and new bookings no longer accept it. Bookings already made keep the text they accepted.'
                                            : 'The draft is removed.'
                                    }
                                    confirmLabel="Delete policy"
                                    destructive
                                    onConfirm={() =>
                                        router.delete(
                                            PolicyController.destroy.url(
                                                policy.slug,
                                            ),
                                        )
                                    }
                                />
                            )}
                        </div>
                    }
                />

                {policy.draft && publishBlocked && can('policies.publish') && (
                    <Alert className="max-w-4xl">
                        <AlertDescription>{publishBlocked}</AlertDescription>
                    </Alert>
                )}

                <Tabs defaultValue="text" className="max-w-4xl">
                    <TabsList>
                        <TabsTrigger value="text">Text</TabsTrigger>
                        <TabsTrigger value="changes">Changes</TabsTrigger>
                        <TabsTrigger value="details">Details</TabsTrigger>
                        <TabsTrigger value="history">
                            History ({policy.versions?.length ?? 0})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="text" className="mt-4">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                draft.put(
                                    PolicyController.saveDraft.url(policy.slug),
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        {policy.draft
                                            ? `Draft version ${policy.draft.version}`
                                            : 'Live text'}
                                    </CardTitle>
                                    <CardDescription>
                                        Edits are saved as a draft. Renters keep
                                        seeing the live text until the draft is
                                        published.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <MarkdownField
                                        name="body"
                                        label="Text"
                                        rows={18}
                                        disabled={!editable}
                                        value={draft.data.body}
                                        error={draft.errors.body}
                                        onChange={(body) =>
                                            draft.setData('body', body)
                                        }
                                    />
                                </CardContent>
                                {editable && (
                                    <CardFooter className="gap-2">
                                        <Button
                                            type="submit"
                                            disabled={
                                                draft.processing || !unsaved
                                            }
                                        >
                                            Save draft
                                        </Button>
                                        {policy.draft && (
                                            <ConfirmDialog
                                                trigger={
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                    >
                                                        Discard draft
                                                    </Button>
                                                }
                                                title="Discard the draft?"
                                                description="The text goes back to the live version."
                                                confirmLabel="Discard"
                                                destructive
                                                onConfirm={() =>
                                                    router.delete(
                                                        PolicyController.discardDraft.url(
                                                            policy.slug,
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            />
                                        )}
                                    </CardFooter>
                                )}
                            </Card>
                        </form>
                    </TabsContent>

                    <TabsContent value="changes" className="mt-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Changes from the live text
                                </CardTitle>
                                <CardDescription>
                                    {policy.published
                                        ? `Compared with version ${policy.published.version}, including edits not saved yet.`
                                        : 'Nothing is live yet, so all the text is new.'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <LineDiff
                                    before={live}
                                    after={draft.data.body}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="details" className="mt-4">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                details.put(
                                    PolicyController.update.url(policy.slug),
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            details.setData('slug', ''),
                                    },
                                );
                            }}
                        >
                            <Card>
                                <CardHeader>
                                    <CardTitle>Details</CardTitle>
                                    <CardDescription>
                                        Saved straight away; these label the
                                        policy and are not part of the text
                                        renters accept.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <DetailsFields
                                        data={details.data}
                                        errors={details.errors}
                                        set={(patch) =>
                                            details.setData({
                                                ...details.data,
                                                ...patch,
                                            })
                                        }
                                        groupOptions={groupOptions}
                                        current={policy.slug}
                                        editable={editable}
                                    />
                                </CardContent>
                                {editable && (
                                    <CardFooter>
                                        <Button
                                            type="submit"
                                            disabled={
                                                details.processing ||
                                                !details.isDirty
                                            }
                                        >
                                            Save details
                                        </Button>
                                    </CardFooter>
                                )}
                            </Card>
                        </form>
                    </TabsContent>

                    <TabsContent value="history" className="mt-4">
                        <History versions={policy.versions ?? []} />
                    </TabsContent>
                </Tabs>
            </AdminPage>
        </>
    );
}

type Details = {
    title: string;
    slug: string;
    group: string;
    summary: string;
    show_on_home: boolean;
};

function DetailsFields({
    data,
    errors,
    set,
    groupOptions,
    current,
    editable,
}: {
    data: Details;
    errors: Partial<Record<keyof Details, string>>;
    set: (patch: Partial<Details>) => void;
    groupOptions: Option[];
    current?: string;
    editable: boolean;
}) {
    return (
        <>
            <TextField
                name="title"
                label="Title"
                required
                disabled={!editable}
                value={data.title}
                error={errors.title}
                onChange={(event) => set({ title: event.target.value })}
            />
            <SlugField
                value={data.slug}
                onChange={(slug) => set({ slug })}
                name={data.title}
                current={current}
                prefix="/rental-policy#"
                error={errors.slug}
                disabled={!editable}
            />
            <SelectField
                name="group"
                label="Section"
                options={groupOptions}
                disabled={!editable}
                value={data.group}
                error={errors.group}
                onChange={(event) => set({ group: event.target.value })}
            />
            <TextareaField
                name="summary"
                label="Summary"
                description="One line for the home page highlights and the policy index."
                rows={2}
                maxLength={500}
                disabled={!editable}
                value={data.summary}
                error={errors.summary}
                onChange={(event) => set({ summary: event.target.value })}
            />
            <ToggleRow
                id="show_on_home"
                label="Show on the home page"
                description="Listed under policy highlights, with its summary."
                checked={data.show_on_home}
                disabled={!editable}
                onChange={(value) => set({ show_on_home: value })}
            />
        </>
    );
}

function History({ versions }: { versions: AdminPolicyVersion[] }) {
    const [open, setOpen] = useState<number | null>(versions[0]?.id ?? null);

    if (versions.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">No versions yet.</p>
        );
    }

    return (
        <ol className="grid gap-3">
            {versions.map((version, index) => {
                const previous = versions[index + 1];

                return (
                    <li key={version.id} className="rounded-md border">
                        <button
                            type="button"
                            className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm"
                            aria-expanded={open === version.id}
                            onClick={() =>
                                setOpen(open === version.id ? null : version.id)
                            }
                        >
                            <span className="font-medium">
                                Version {version.version}
                            </span>
                            <span className="flex-1 text-muted-foreground">
                                {version.published_at
                                    ? `Published ${formatDateTime(version.published_at)}`
                                    : 'Draft'}
                                {version.created_by &&
                                    ` · ${version.created_by}`}
                            </span>
                        </button>
                        {open === version.id && (
                            <div className="border-t p-4">
                                <p className="mb-2 text-xs text-muted-foreground">
                                    {previous
                                        ? `Changes from version ${previous.version}`
                                        : 'The first version'}
                                </p>
                                <LineDiff
                                    before={previous?.body ?? ''}
                                    after={version.body}
                                />
                            </div>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

PolicyForm.layout = {
    breadcrumbs: [
        { title: 'Content', href: ContentController() },
        { title: 'Policies', href: PolicyController.index() },
    ],
};
