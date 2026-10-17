import { describe, expect, it } from 'vite-plus/test';
import { can, permissionsFor } from '../can';

describe('can', () => {
    it('allows anything for a super admin', () => {
        expect(can(['*'], 'users.delete')).toBe(true);
    });

    it('checks a single permission', () => {
        expect(can(['users.view'], 'users.view')).toBe(true);
        expect(can(['users.view'], 'users.delete')).toBe(false);
    });

    it('accepts any permission from a list', () => {
        expect(
            can(
                ['settings.general.update'],
                ['settings.fx.update', 'settings.general.update'],
            ),
        ).toBe(true);
        expect(can([], ['settings.fx.update'])).toBe(false);
    });

    it('allows items without a requirement', () => {
        expect(can([], undefined)).toBe(true);
    });
});

describe('permissionsFor', () => {
    it('lists a module’s permissions', () => {
        expect(permissionsFor('activity')).toEqual(['activity.view']);
        expect(permissionsFor('settings')).toContain('settings.general.update');
        expect(permissionsFor('nope')).toEqual([]);
    });
});
