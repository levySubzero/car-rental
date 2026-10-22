import { about, contact, fleet, home, rentalPolicy, services } from '@/routes';

// Main navigation (M7.2). Car types are listed under "Fleet" from the shared `site` prop.
export const NAV_ITEMS = [
    { label: 'Home', href: home().url },
    { label: 'Fleet', href: fleet().url },
    { label: 'Services', href: services().url },
    { label: 'About', href: about().url },
    { label: 'Rental policy', href: rentalPolicy().url },
    { label: 'Contact', href: contact().url },
];

export function isActive(current: string, href: string): boolean {
    const path = current.split('?')[0];

    return href === '/' ? path === '/' : path.startsWith(href);
}
