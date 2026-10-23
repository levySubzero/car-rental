import { Link, usePage } from '@inertiajs/react';
import { CalendarCheck, ChevronDown, Menu } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BrandIcon } from '@/components/public/icons';
import { isActive, NAV_ITEMS } from '@/components/public/nav-items';
import { SocialLinks } from '@/components/public/social-links';
import { Wordmark } from '@/components/public/wordmark';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useSite } from '@/hooks/use-site';
import { cn } from '@/lib/utils';
import { fleet } from '@/routes';
import { show as carTypeShow } from '@/routes/car-types';
import { index as myBookings } from '@/routes/portal';

const linkClass =
    'relative py-2 text-sm font-medium tracking-wide text-white/80 transition-colors hover:text-white focus-visible:text-gold focus-visible:outline-none';

/**
 * Sticky header (M7.2). Transparent over the hero, solid once the page scrolls.
 */
export function SiteHeader() {
    const { url } = usePage();
    const { site, whatsapp } = useSite();
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 24);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const enquire = whatsapp();

    return (
        <header
            className={cn(
                'sticky top-0 z-40 border-b transition-colors duration-300',
                scrolled
                    ? 'border-white/10 bg-ink/95 backdrop-blur'
                    : 'border-transparent bg-ink/60 backdrop-blur-sm',
            )}
        >
            <div className="mx-auto flex h-18 max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
                <Wordmark />

                <nav aria-label="Main" className="hidden lg:block">
                    <ul className="flex items-center gap-8">
                        {NAV_ITEMS.map((item) => {
                            const active = isActive(url, item.href);

                            if (
                                item.href === fleet().url &&
                                site.car_types.length > 0
                            ) {
                                return (
                                    <li key={item.href}>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger
                                                className={cn(
                                                    linkClass,
                                                    'inline-flex items-center gap-1',
                                                    active && 'text-gold',
                                                )}
                                            >
                                                {item.label}
                                                <ChevronDown
                                                    className="size-3.5"
                                                    aria-hidden
                                                />
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent className="public dark w-56">
                                                <DropdownMenuItem asChild>
                                                    <Link href={fleet()}>
                                                        All cars
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                {site.car_types.map((type) => (
                                                    <DropdownMenuItem
                                                        key={type.slug}
                                                        asChild
                                                    >
                                                        <Link
                                                            href={carTypeShow(
                                                                type.slug,
                                                            )}
                                                        >
                                                            {type.name}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                ))}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </li>
                                );
                            }

                            return (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={cn(
                                            linkClass,
                                            active && 'text-gold',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <div className="flex items-center gap-2">
                    <Button
                        asChild
                        variant="ghost"
                        className="px-2 text-white/80 hover:text-white sm:px-3"
                    >
                        <Link href={myBookings()}>
                            <CalendarCheck className="size-4" />
                            <span className="sr-only sm:not-sr-only">
                                My bookings
                            </span>
                        </Link>
                    </Button>
                    {enquire && (
                        <Button
                            asChild
                            className="hidden font-semibold sm:inline-flex"
                        >
                            <a
                                href={enquire}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <BrandIcon name="whatsapp" className="size-4" />
                                Enquire
                            </a>
                        </Button>
                    )}
                    <MobileMenu />
                </div>
            </div>
        </header>
    );
}

function MobileMenu() {
    const { url } = usePage();
    const { site, phone, telHref } = useSite();
    const [open, setOpen] = useState(false);

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="text-white lg:hidden"
                    aria-label="Open menu"
                >
                    <Menu className="size-6" />
                </Button>
            </SheetTrigger>
            <SheetContent
                side="right"
                className="public dark w-full max-w-sm border-white/10 bg-ink text-foreground"
            >
                <SheetTitle className="sr-only">Menu</SheetTitle>
                <SheetDescription className="sr-only">
                    Site navigation
                </SheetDescription>
                <div className="flex h-full flex-col overflow-y-auto px-6 pt-6 pb-8">
                    <Wordmark />
                    <nav aria-label="Mobile" className="mt-10">
                        <ul className="space-y-1">
                            {NAV_ITEMS.map((item) => (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        onClick={() => setOpen(false)}
                                        aria-current={
                                            isActive(url, item.href)
                                                ? 'page'
                                                : undefined
                                        }
                                        className={cn(
                                            'block border-b border-white/5 py-3 font-display text-xl text-white/90 transition-colors hover:text-gold',
                                            isActive(url, item.href) &&
                                                'text-gold',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>
                    {site.car_types.length > 0 && (
                        <div className="mt-8">
                            <p className="eyebrow">Car types</p>
                            <ul className="mt-3 flex flex-wrap gap-2">
                                {site.car_types.map((type) => (
                                    <li key={type.slug}>
                                        <Link
                                            href={carTypeShow(type.slug)}
                                            onClick={() => setOpen(false)}
                                            className="block rounded-full border border-white/10 px-3 py-1.5 text-sm text-white/80 transition-colors hover:border-gold hover:text-gold"
                                        >
                                            {type.name}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <div className="mt-auto space-y-4 pt-10">
                        {phone && telHref && (
                            <a
                                href={telHref}
                                className="block text-lg text-white"
                            >
                                {phone}
                            </a>
                        )}
                        <SocialLinks iconClassName="size-5" />
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
