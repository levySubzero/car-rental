import { describe, expect, it } from 'vite-plus/test';
import { fromLocalInput, toLocalInput } from '../booking';

describe('booking times', () => {
    it('reads and writes datetime-local values as Nairobi time', () => {
        expect(fromLocalInput('2026-11-10T10:00')).toBe(
            '2026-11-10T10:00:00+03:00',
        );
        expect(toLocalInput('2026-11-10T07:00:00Z')).toBe('2026-11-10T10:00');
        expect(toLocalInput(fromLocalInput('2026-12-31T23:30'))).toBe(
            '2026-12-31T23:30',
        );
        expect(fromLocalInput('')).toBe('');
        expect(toLocalInput(null)).toBe('');
    });
});
