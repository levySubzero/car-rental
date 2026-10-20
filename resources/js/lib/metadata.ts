/**
 * Helpers for `metadata` objects (D20): informal extras stored as snake_case key → text.
 * The editor works on rows so a half-typed key never loses its value; the form submits
 * the object built by metadataFromRows().
 */

export type MetadataRow = { key: string; value: string };

// Matches App\Support\Rules\MetadataKeys.
const KEY_PATTERN = /^[a-z][a-z0-9_]{0,39}$/;

/**
 * "Top speed (km/h)" → "top_speed_km_h". Keys that would start with a digit get an "x".
 */
export function toMetadataKey(input: string): string {
    const key = input
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

    return (/^\d/.test(key) ? `x_${key}` : key).slice(0, 40);
}

export function isValidMetadataKey(key: string): boolean {
    return KEY_PATTERN.test(key);
}

/**
 * "zero_to_hundred_s" → "Zero to hundred s", for showing a key as a label.
 */
export function metadataLabel(key: string): string {
    const words = key.replace(/_/g, ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

export function rowsFromMetadata(
    metadata: Record<string, string> | null | undefined,
): MetadataRow[] {
    return Object.entries(metadata ?? {}).map(([key, value]) => ({
        key,
        value,
    }));
}

/**
 * Rows without a key or value are dropped; a repeated key keeps its last value.
 */
export function metadataFromRows(rows: MetadataRow[]): Record<string, string> {
    const metadata: Record<string, string> = {};

    for (const { key, value } of rows) {
        const trimmedKey = key.trim();
        const trimmedValue = value.trim();

        if (trimmedKey !== '' && trimmedValue !== '') {
            metadata[trimmedKey] = trimmedValue;
        }
    }

    return metadata;
}

export function duplicateMetadataKeys(rows: MetadataRow[]): string[] {
    const seen = new Set<string>();
    const duplicates = new Set<string>();

    for (const { key } of rows) {
        if (key === '') {
            continue;
        }

        if (seen.has(key)) {
            duplicates.add(key);
        }

        seen.add(key);
    }

    return [...duplicates];
}
