import { describe, expect, it } from 'vite-plus/test';
import { scrubUrl } from '../sentry';

// The same masks as App\Support\SentryScrubber (tests/Unit/Support/SentryScrubberTest.php).
describe('scrubUrl', () => {
    it('masks booking tokens and the Daraja secret', () => {
        expect(
            scrubUrl(
                `https://baines.test/bookings/${'aB3'.repeat(21)}x/driver`,
            ),
        ).toBe('https://baines.test/bookings/[token]/driver');
        expect(
            scrubUrl('https://baines.test/hooks/mobile/c2b/s3cret/confirm'),
        ).toBe('https://baines.test/hooks/mobile/c2b/[secret]/confirm');
        expect(scrubUrl('https://baines.test/fleet?brand=mazda')).toBe(
            'https://baines.test/fleet?brand=mazda',
        );
    });
});
