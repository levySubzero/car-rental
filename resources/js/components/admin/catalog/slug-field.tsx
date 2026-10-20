import { TextField } from '@/components/admin/form-field';
import { slugify } from '@/lib/slug';

/**
 * A slug input for useForm pages. Left empty, the server generates the slug from the
 * name on create and keeps the current one on update.
 */
export function SlugField({
    value,
    onChange,
    name,
    current,
    prefix,
    error,
    disabled,
}: {
    value: string;
    onChange: (slug: string) => void;
    // The record's name, for the placeholder on new records.
    name: string;
    // The saved slug of an existing record.
    current?: string;
    // The public path before the slug, e.g. "/fleet/".
    prefix: string;
    error?: string;
    disabled?: boolean;
}) {
    const preview = value || current || slugify(name);

    return (
        <TextField
            name="slug"
            label="URL slug"
            value={value}
            placeholder={
                current ?? (slugify(name) || 'generated-from-the-name')
            }
            disabled={disabled}
            error={error}
            onChange={(event) => onChange(slugify(event.target.value))}
            description={
                preview
                    ? `${prefix}${preview}${current && value && value !== current ? ' · changing it breaks old links' : ''}`
                    : 'Generated from the name.'
            }
        />
    );
}
