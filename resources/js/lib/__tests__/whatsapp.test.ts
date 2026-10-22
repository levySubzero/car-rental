import { describe, expect, it } from 'vite-plus/test';
import { fillTemplate, whatsappLink } from '../whatsapp';

// The same cases are in tests/Feature/Support/WhatsAppLinkBuilderTest.php.
describe('fillTemplate', () => {
    it.each([
        [
            'Rent the {car} for {dates}.',
            { car: 'Mazda Demio', dates: '3–5 Nov' },
            'Rent the Mazda Demio for 3–5 Nov.',
        ],
        [
            'Rent the {car} for {dates}.',
            { car: 'Mazda Demio' },
            'Rent the Mazda Demio.',
        ],
        [
            'About booking {reference} on {dates}, thanks',
            { reference: 'BX-1', dates: '  ' },
            'About booking BX-1, thanks',
        ],
        ['Hello there.', {}, 'Hello there.'],
    ])('fills %s', (template, values, expected) => {
        expect(fillTemplate(template, values)).toBe(expected);
    });
});

describe('whatsappLink', () => {
    it('encodes like PHP rawurlencode', () => {
        expect(
            whatsappLink(
                '+254 724 422 051',
                "Hi, I'm interested in the {car} for {dates}.",
                { car: 'Mazda Demio' },
            ),
        ).toBe(
            'https://wa.me/254724422051?text=Hi%2C%20I%27m%20interested%20in%20the%20Mazda%20Demio.',
        );
    });

    it('is null without a number', () => {
        expect(whatsappLink(null, 'Hello')).toBeNull();
    });
});
