import { useState } from 'react';
import { TextField } from '@/components/admin/form-field';
import { slugify } from '@/lib/slug';

/**
 * Follows `source` (usually the name field) until the user edits the slug by hand.
 * Existing records start detached so their public URL never changes by accident.
 */
export function SlugInput({
    source,
    defaultValue = '',
    name = 'slug',
    label = 'URL slug',
    error,
    prefix,
}: {
    source: string;
    defaultValue?: string;
    name?: string;
    label?: string;
    error?: string;
    // Shown before the input, e.g. "/fleet/".
    prefix?: string;
}) {
    const [manual, setManual] = useState<string | null>(
        defaultValue === '' ? null : defaultValue,
    );
    const value = manual ?? slugify(source);

    return (
        <TextField
            name={name}
            label={label}
            error={error}
            value={value}
            onChange={(event) => setManual(slugify(event.target.value))}
            description={prefix ? `${prefix}${value}` : undefined}
        />
    );
}
