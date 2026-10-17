import { describe, expect, it } from 'vite-plus/test';
import { cn } from '../utils';

describe('cn', () => {
    it('merges conflicting tailwind classes, last one wins', () => {
        expect(cn('px-2', 'px-4')).toBe('px-4');
    });

    it('drops falsy values', () => {
        const hidden = false;
        expect(cn('a', hidden && 'b', undefined, 'c')).toBe('a c');
    });
});
