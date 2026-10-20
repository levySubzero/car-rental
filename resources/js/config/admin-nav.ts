import {
    CarFront,
    History,
    Inbox,
    LayoutGrid,
    PanelsTopLeft,
    Shapes,
    Settings2,
    ShieldCheck,
    Tags,
    Users,
} from 'lucide-react';
import ActivityLogController from '@/actions/App/Http/Controllers/Admin/ActivityLogController';
import BrandController from '@/actions/App/Http/Controllers/Admin/BrandController';
import CarController from '@/actions/App/Http/Controllers/Admin/CarController';
import CarTypeController from '@/actions/App/Http/Controllers/Admin/CarTypeController';
import ContentController from '@/actions/App/Http/Controllers/Admin/ContentController';
import ContactMessageController from '@/actions/App/Http/Controllers/Admin/ContactMessageController';
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
        // Day-to-day desk work; bookings and payments join in M11.
        title: 'Operations',
        items: [
            {
                title: 'Messages',
                href: ContactMessageController.index(),
                icon: Inbox,
                permission: 'contact-messages.view',
            },
        ],
    },
    {
        title: 'Catalog',
        items: [
            {
                title: 'Cars',
                href: CarController.index(),
                icon: CarFront,
                permission: 'cars.view',
            },
            {
                title: 'Brands',
                href: BrandController.index(),
                icon: Tags,
                permission: 'brands.view',
            },
            {
                title: 'Car types',
                href: CarTypeController.index(),
                icon: Shapes,
                permission: 'car-types.view',
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
                // Pages, services, policies and payment methods, as tabs.
                title: 'Content',
                href: ContentController(),
                icon: PanelsTopLeft,
                permission: [
                    'pages.view',
                    'services.view',
                    'policies.view',
                    'payment-methods.view',
                ],
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
