import { describe, expect, it } from 'vitest';
import {
    duplicateMetadataKeys,
    isValidMetadataKey,
    metadataFromRows,
    metadataLabel,
    rowsFromMetadata,
    toMetadataKey,
} from '@/lib/metadata';

describe('toMetadataKey', () => {
    it('turns a label into a snake_case key', () => {
        expect(toMetadataKey('Top speed (km/h)')).toBe('top_speed_km_h');
        expect(toMetadataKey('  0–100 time ')).toBe('x_0_100_time');
        expect(toMetadataKey('Côté')).toBe('cote');
    });

    it('produces keys the server accepts', () => {
        for (const label of [
            'Luggage bags',
            '4WD',
            'Features!!',
            'a'.repeat(60),
        ]) {
            expect(isValidMetadataKey(toMetadataKey(label))).toBe(true);
        }
    });
});

describe('metadata rows', () => {
    it('round-trips an object', () => {
        const metadata = { drive: '4WD', luggage_bags: '5' };

        expect(metadataFromRows(rowsFromMetadata(metadata))).toEqual(metadata);
    });

    it('drops empty rows and trims values', () => {
        expect(
            metadataFromRows([
                { key: 'drive', value: ' AWD ' },
                { key: '', value: 'orphan' },
                { key: 'colour_code', value: '  ' },
            ]),
        ).toEqual({ drive: 'AWD' });
    });

    it('reports repeated keys', () => {
        expect(
            duplicateMetadataKeys([
                { key: 'drive', value: 'AWD' },
                { key: 'seats', value: '5' },
                { key: 'drive', value: '4WD' },
            ]),
        ).toEqual(['drive']);
    });

    it('labels keys for display', () => {
        expect(metadataLabel('zero_to_hundred_s')).toBe('Zero to hundred s');
    });
});
