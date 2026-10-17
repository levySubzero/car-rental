/**
 * Mirrors Laravel's Str::slug for the Latin text we use: lower case, ASCII,
 * words joined by hyphens.
 */
export function slugify(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/&/g, ' and ')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
