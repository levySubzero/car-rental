import {
    History,
    LayoutGrid,
    Settings2,
    ShieldCheck,
    Users,
} from 'lucide-react';
import ActivityLogController from '@/actions/App/Http/Controllers/Admin/ActivityLogController';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { permissionsFor } from '@/lib/can';
import { dashboard } from '@/routes/admin';
import type { NavGroup } from '@/types';

/**
 * Admin sidebar. Each module adds its entry here with the permission that unlocks it.
 * Groups with no visible items are hidden.
 */
export const adminNav: NavGroup[] = [
    {
        title: 'Overview',
        items: [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
                exact: true,
            },
        ],
    },
    {
        title: 'System',
        items: [
            {
                title: 'Users',
                href: UserController.index(),
                icon: Users,
                permission: 'users.view',
            },
            {
                title: 'Roles',
                href: RoleController.index(),
                icon: ShieldCheck,
                permission: 'roles.view',
            },
            {
                title: 'Settings',
                href: SettingsController.index(),
                icon: Settings2,
                permission: permissionsFor('settings'),
            },
            {
                title: 'Activity log',
                href: ActivityLogController.index(),
                icon: History,
                permission: 'activity.view',
            },
        ],
    },
];
