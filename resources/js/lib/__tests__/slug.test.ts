import { describe, expect, it } from 'vite-plus/test';
import { slugify } from '../slug';

describe('slugify', () => {
    it('matches the server slug for typical names', () => {
        expect(slugify('Toyota Land Cruiser V8')).toBe(
            'toyota-land-cruiser-v8',
        );
        expect(slugify('  Mercedes-Benz  G 63 ')).toBe('mercedes-benz-g-63');
    });

    it('strips accents and symbols', () => {
        expect(slugify('Citroën C5 Aircross!')).toBe('citroen-c5-aircross');
        expect(slugify('Safaris & Tours')).toBe('safaris-and-tours');
    });
});
