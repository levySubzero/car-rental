import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { Permission } from './permissions';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    // Highlight only on this exact URL instead of on any URL below it.
    exact?: boolean;
    // Shown only when the user has this permission, or any of them when given a list.
    permission?: Permission | readonly Permission[];
};

export type NavGroup = {
    title: string;
    items: NavItem[];
};
