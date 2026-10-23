/*
 * wa.me links with a prefilled message (M7.3). Mirrors App\Support\WhatsAppLinkBuilder:
 * templates use {car}, {dates} and {reference}, and a placeholder without a value is
 * dropped with a "for" or "on" in front of it.
 */

export type WhatsAppTemplates = Record<string, string>;

export function fillTemplate(
    template: string,
    values: Record<string, string | null | undefined>,
): string {
    const text = template.replace(
        /(\s+(?:for|on))?\s*\{(\w+)\}/g,
        (match: string, _prefix: string | undefined, key: string) => {
            const value = (values[key] ?? '').trim();

            return value === ''
                ? ''
                : match.slice(0, match.indexOf('{')) + value;
        },
    );

    return text.replace(/\s{2,}/g, ' ').trim();
}

/**
 * Null when no WhatsApp number is set yet.
 */
export function whatsappLink(
    number: string | null,
    template: string | undefined,
    values: Record<string, string | null | undefined> = {},
): string | null {
    let digits = (number ?? '').replace(/\D+/g, '');

    if (digits === '') {
        return null;
    }

    // Numbers written locally ("0712 345 678") are taken as Kenyan.
    if (digits.startsWith('0')) {
        digits = `254${digits.slice(1)}`;
    }

    const text = fillTemplate(template ?? '', values);

    return `https://wa.me/${digits}${text === '' ? '' : `?text=${rawUrlEncode(text)}`}`;
}

// PHP's rawurlencode, so links match the server's byte for byte ("I'm" → "I%27m").
function rawUrlEncode(text: string): string {
    return encodeURIComponent(text).replace(
        /[!'()*]/g,
        (char) => `%${char.charCodeAt(0).toString(16).toUpperCase()}`,
    );
}
