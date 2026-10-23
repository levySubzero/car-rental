/*
 * Booking times are entered and shown in the business time zone (D30), whatever the
 * renter's device says. Kenya keeps UTC+3 all year, so a fixed offset is exact.
 */

const ZONE = 'Africa/Nairobi';
const OFFSET = '+03:00';

const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: ZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

const display = new Intl.DateTimeFormat('en-KE', {
    timeZone: ZONE,
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

/**
 * An instant as a `datetime-local` value in Nairobi time: "2026-11-10T10:00".
 */
export function toLocalInput(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const get = (type: string) =>
        parts.formatToParts(new Date(iso)).find((part) => part.type === type)
            ?.value ?? '';

    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
}

/**
 * A `datetime-local` value read as Nairobi time: "2026-11-10T10:00" → "2026-11-10T10:00:00+03:00".
 */
export function fromLocalInput(value: string): string {
    return value ? `${value}:00${OFFSET}` : '';
}

/**
 * "Tue, 10 Nov 2026, 10:00" in Nairobi time.
 */
export function formatBookingTime(iso: string | null | undefined): string {
    return iso ? display.format(new Date(iso)) : '—';
}
