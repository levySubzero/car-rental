import { usePage } from '@inertiajs/react';
import { can } from '@/lib/can';
import type { Permission } from '@/types';

export function useCan(): (
    required?: Permission | readonly Permission[],
) => boolean {
    const { auth } = usePage().props;
    const permissions = auth.permissions ?? [];

    return (required) => can(permissions, required);
}
