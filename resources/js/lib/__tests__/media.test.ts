import { describe, expect, it } from 'vite-plus/test';
import type { MediaCollectionLimits, MediaItem } from '@/types';
import {
    acceptAttribute,
    compressIfNeeded,
    coverOf,
    formatBytes,
    formatKilobytes,
    rejectionFor,
} from '../media';

const limits: MediaCollectionLimits = {
    key: 'car.gallery',
    label: 'Car gallery',
    visibility: 'public',
    max_files: 6,
    max_kb: 2048,
    mimes: ['jpg', 'png', 'heic'],
    accept: ['image/jpeg', 'image/png', 'image/heic', 'image/heif'],
};

const file = (name: string, bytes: number, type = 'image/jpeg') =>
    new File([new Uint8Array(bytes)], name, { type });

const item = (uuid: string) => ({ uuid }) as MediaItem;

describe('formatBytes', () => {
    it('picks a readable unit', () => {
        expect(formatBytes(512)).toBe('512 B');
        expect(formatBytes(2048)).toBe('2 KB');
        expect(formatBytes(1536 * 1024)).toBe('1.5 MB');
        expect(formatBytes(8 * 1024 * 1024)).toBe('8 MB');
    });

    it('formats collection limits given in kilobytes', () => {
        expect(formatKilobytes(8192)).toBe('8 MB');
        expect(formatKilobytes(500)).toBe('500 KB');
    });
});

describe('acceptAttribute', () => {
    it('lists MIME types and extensions, for browsers that do not know HEIC', () => {
        expect(acceptAttribute(limits)).toBe(
            'image/jpeg,image/png,image/heic,image/heif,.jpg,.png,.heic',
        );
    });
});

describe('rejectionFor', () => {
    it('accepts allowed extensions in any case, including .jpeg', () => {
        expect(rejectionFor(file('IMG_1.JPEG', 10), limits)).toBeNull();
        expect(rejectionFor(file('photo.heic', 10), limits)).toBeNull();
    });

    it('refuses other types and oversize files', () => {
        expect(rejectionFor(file('scan.pdf', 10), limits)).toBe(
            'Use JPG, PNG, HEIC.',
        );
        expect(rejectionFor(file('big.png', 2049 * 1024), limits)).toBe(
            'Larger than 2 MB.',
        );
    });
});

describe('compressIfNeeded', () => {
    it('leaves small images, PDFs and HEIC untouched', async () => {
        const small = file('a.jpg', 1000);
        const pdf = file('a.pdf', 5 * 1024 * 1024, 'application/pdf');
        const heic = file('a.heic', 5 * 1024 * 1024, 'image/heic');

        expect(await compressIfNeeded(small, 8192)).toBe(small);
        expect(await compressIfNeeded(pdf, 8192)).toBe(pdf);
        expect(await compressIfNeeded(heic, 8192)).toBe(heic);
    });

    it('falls back to the original when compression fails', async () => {
        // Not a decodable image: the library throws, the upload goes ahead unchanged.
        const broken = file('broken.jpg', 3 * 1024 * 1024);

        expect(await compressIfNeeded(broken, 8192)).toBe(broken);
    });
});

describe('coverOf', () => {
    it('keeps a chosen cover that is still in the list, else uses the first file', () => {
        const items = [item('a'), item('b')];

        expect(coverOf(items, 'b')).toBe('b');
        expect(coverOf(items, 'gone')).toBe('a');
        expect(coverOf(items)).toBe('a');
        expect(coverOf([], 'a')).toBeNull();
    });
});
