import { useForm } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import { Field, TextField } from '@/components/admin/form-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { formatKilobytes } from '@/lib/media';
import type { MediaCollectionLimits } from '@/types';

export type MediaSettingsContext = {
    collections: (MediaCollectionLimits & {
        effective: MediaCollectionLimits;
    })[];
};

type CollectionInput = { max_files: string; max_kb: string; mimes: string[] };

export function MediaSettingsForm({
    context,
}: {
    context: MediaSettingsContext;
}) {
    const form = useForm<{ collections: Record<string, CollectionInput> }>({
        collections: Object.fromEntries(
            context.collections.map((collection) => [
                collection.key,
                {
                    max_files: String(collection.effective.max_files),
                    max_kb: String(collection.effective.max_kb),
                    mimes: collection.effective.mimes,
                },
            ]),
        ),
    });

    const errors = form.errors as Record<string, string | undefined>;

    const update = (key: string, patch: Partial<CollectionInput>) =>
        form.setData('collections', {
            ...form.data.collections,
            [key]: { ...form.data.collections[key], ...patch },
        });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(SettingsController.update.url('media'), {
                    preserveScroll: true,
                });
            }}
        >
            <Card>
                <CardHeader>
                    <CardTitle>Uploads</CardTitle>
                    <CardDescription>
                        Limits for each kind of upload. You can lower a limit or
                        switch off file types, but never go above what the
                        system allows. New limits apply to new uploads only.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6">
                    {context.collections.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No upload types yet.
                        </p>
                    )}
                    {context.collections.map((collection) => {
                        const data = form.data.collections[collection.key];
                        const prefix = `collections.${collection.key}`;

                        return (
                            <fieldset
                                key={collection.key}
                                className="grid gap-4 rounded-lg border p-4 sm:grid-cols-3"
                            >
                                <legend className="px-1 text-sm font-medium">
                                    {collection.label}
                                    <span className="ml-2 text-xs font-normal text-muted-foreground">
                                        {collection.visibility === 'private'
                                            ? 'Private'
                                            : 'Public'}
                                    </span>
                                </legend>
                                <TextField
                                    name={`${prefix}.max_files`}
                                    label="Files allowed"
                                    type="number"
                                    min={1}
                                    max={collection.max_files}
                                    required
                                    value={data.max_files}
                                    error={errors[`${prefix}.max_files`]}
                                    description={`At most ${collection.max_files}`}
                                    onChange={(event) =>
                                        update(collection.key, {
                                            max_files: event.target.value,
                                        })
                                    }
                                />
                                <TextField
                                    name={`${prefix}.max_kb`}
                                    label="Largest file (KB)"
                                    type="number"
                                    min={1}
                                    max={collection.max_kb}
                                    required
                                    value={data.max_kb}
                                    error={errors[`${prefix}.max_kb`]}
                                    description={`At most ${formatKilobytes(collection.max_kb)}`}
                                    onChange={(event) =>
                                        update(collection.key, {
                                            max_kb: event.target.value,
                                        })
                                    }
                                />
                                <Field
                                    label="File types"
                                    error={
                                        errors[`${prefix}.mimes`] ??
                                        errors[`${prefix}.mimes.0`]
                                    }
                                >
                                    <div className="flex flex-wrap gap-3">
                                        {collection.mimes.map((extension) => {
                                            const id = `${collection.key}-${extension}`;

                                            return (
                                                <div
                                                    key={extension}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Checkbox
                                                        id={id}
                                                        checked={data.mimes.includes(
                                                            extension,
                                                        )}
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            update(
                                                                collection.key,
                                                                {
                                                                    mimes: checked
                                                                        ? [
                                                                              ...data.mimes,
                                                                              extension,
                                                                          ]
                                                                        : data.mimes.filter(
                                                                              (
                                                                                  item,
                                                                              ) =>
                                                                                  item !==
                                                                                  extension,
                                                                          ),
                                                                },
                                                            )
                                                        }
                                                    />
                                                    <Label
                                                        htmlFor={id}
                                                        className="font-normal uppercase"
                                                    >
                                                        {extension}
                                                    </Label>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </Field>
                            </fieldset>
                        );
                    })}
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save upload settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
