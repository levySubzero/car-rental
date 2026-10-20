import { Field, TextareaField, TextField } from '@/components/admin/form-field';
import { MarkdownField } from '@/components/admin/markdown-field';
import { MediaUploader } from '@/components/media/media-uploader';
import type {
    ContentFieldDefinition,
    ContentListItem,
    ContentValue,
    MediaCollectionLimits,
    MediaItem,
} from '@/types';

type Errors = Record<string, string | undefined>;

/**
 * One field of a preset page (config/pages.php), by type: text, Markdown, image or a
 * fixed-length list. Errors are the form's bag, keyed "content.{key}" and
 * "content.{key}.{index}.{subkey}".
 */
export function ContentField({
    field,
    value,
    onChange,
    errors,
    images,
    onImage,
    imageLimits,
    disabled,
}: {
    field: ContentFieldDefinition;
    value: ContentValue;
    onChange: (value: ContentValue) => void;
    errors: Errors;
    // Files already known to the page, keyed by uuid, so image fields can show them.
    images: Record<string, MediaItem>;
    onImage: (item: MediaItem) => void;
    imageLimits: MediaCollectionLimits;
    disabled: boolean;
}) {
    const name = `content.${field.key}`;
    const label = field.required ? field.label : `${field.label} (optional)`;

    if (field.type === 'markdown') {
        return (
            <MarkdownField
                name={name}
                label={label}
                description={field.help}
                maxLength={field.max}
                disabled={disabled}
                value={(value as string | null) ?? ''}
                error={errors[name]}
                onChange={onChange}
            />
        );
    }

    if (field.type === 'image') {
        const uuid = value as string | null;
        const current = uuid && images[uuid] ? [images[uuid]] : [];

        return (
            <Field label={label} error={errors[name]} description={field.help}>
                <MediaUploader
                    limits={{ ...imageLimits, max_files: 1 }}
                    value={current}
                    disabled={disabled}
                    onChange={(items) => {
                        if (items[0]) {
                            onImage(items[0]);
                        }

                        onChange(items[0]?.uuid ?? null);
                    }}
                />
            </Field>
        );
    }

    if (field.type === 'list') {
        const items = (value as ContentListItem[] | null) ?? [];

        return (
            <fieldset className="grid gap-3">
                <legend className="text-sm font-medium">{label}</legend>
                {field.help && (
                    <p className="-mt-1 text-xs text-muted-foreground">
                        {field.help}
                    </p>
                )}
                <ol className="grid gap-3">
                    {items.map((item, index) => (
                        <li
                            key={index}
                            className="grid gap-3 rounded-md border p-3 sm:grid-cols-[2.5rem_minmax(0,1fr)]"
                        >
                            <span className="font-mono text-sm text-muted-foreground tabular-nums">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <div className="grid gap-3">
                                {field.fields.map((sub) => {
                                    const subName = `${name}.${index}.${sub.key}`;
                                    const props = {
                                        name: subName,
                                        label: sub.label,
                                        maxLength: sub.max,
                                        disabled,
                                        value: item[sub.key] ?? '',
                                        error: errors[subName],
                                        onChange: (
                                            event: React.ChangeEvent<
                                                | HTMLInputElement
                                                | HTMLTextAreaElement
                                            >,
                                        ) =>
                                            onChange(
                                                items.map((current, i) =>
                                                    i === index
                                                        ? {
                                                              ...current,
                                                              [sub.key]:
                                                                  event.target
                                                                      .value,
                                                          }
                                                        : current,
                                                ),
                                            ),
                                    };

                                    return sub.multiline ? (
                                        <TextareaField
                                            key={sub.key}
                                            rows={2}
                                            {...props}
                                        />
                                    ) : (
                                        <TextField key={sub.key} {...props} />
                                    );
                                })}
                            </div>
                        </li>
                    ))}
                </ol>
            </fieldset>
        );
    }

    const props = {
        name,
        label,
        description: field.help,
        maxLength: field.max,
        disabled,
        value: (value as string | null) ?? '',
        error: errors[name],
        onChange: (
            event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>,
        ) => onChange(event.target.value),
    };

    return field.multiline ? (
        <TextareaField rows={3} {...props} />
    ) : (
        <TextField {...props} />
    );
}
