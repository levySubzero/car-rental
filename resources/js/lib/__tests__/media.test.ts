import { describe, expect, it } from 'vite-plus/test';
import { formatBytes, formatKilobytes } from '../media';

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
