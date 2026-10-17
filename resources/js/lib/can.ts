import { permissions } from '@/types/permissions';
import type { Permission } from '@/types/permissions';

/**
 * Whether a permission list grants an ability. Used to hide UI the user cannot use;
 * the server still authorizes every request.
 *
 * `required` may be a single permission or a list, in which case any one is enough.
 */
export function can(
    granted: readonly (Permission | '*')[],
    required?: Permission | readonly Permission[],
): boolean {
    if (required === undefined) {
        return true;
    }

    if (granted.includes('*')) {
        return true;
    }

    const wanted: readonly Permission[] =
        typeof required === 'string' ? [required] : required;

    return wanted.some((permission) => granted.includes(permission));
}

/**
 * Every permission of a module, e.g. permissionsFor('settings').
 */
export function permissionsFor(module: string): Permission[] {
    return permissions.filter((permission) =>
        permission.startsWith(`${module}.`),
    );
}
