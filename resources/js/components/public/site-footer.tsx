import { Link, usePage } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';
import type { ReactNode } from 'react';
import { BrandIcon } from '@/components/public/icons';
import { NAV_ITEMS } from '@/components/public/nav-items';
import { SocialLinks } from '@/components/public/social-links';
import { Wordmark } from '@/components/public/wordmark';
import { useSite } from '@/hooks/use-site';
import { privacy, rentalPolicy } from '@/routes';
import { show as brandShow } from '@/routes/car-brands';
import { show as carTypeShow } from '@/routes/car-types';

/**
 * Footer (M7.4): about, car types, brands, links, contacts with map, payment methods.
 */
export function SiteFooter() {
    const { site, phone, telHref, whatsapp } = useSite();
    // The Contact page shows the map beside the form instead.
    const { component } = usePage();
    const mapUrl = component === 'public/contact' ? null : site.map_embed_url;
    const chat = whatsapp();

    return (
        <footer className="border-t border-white/5 bg-surface text-sm text-white/70">
            <div className="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 md:grid-cols-2 lg:grid-cols-12 lg:px-8">
                <div className="lg:col-span-3">
                    <Wordmark />
                    <p className="mt-6 max-w-sm leading-relaxed">
                        Executive car rental, chauffeur service and safaris in
                        Nairobi. Self-drive or chauffeur-driven, delivered to
                        your door.
                    </p>
                    <SocialLinks className="mt-6" iconClassName="size-5" />
                </div>

                <FooterColumn title="Car types" className="lg:col-span-2">
                    {site.car_types.map((type) => (
                        <FooterLink
                            key={type.slug}
                            href={carTypeShow(type.slug).url}
                        >
                            {type.name}
                        </FooterLink>
                    ))}
                </FooterColumn>

                <FooterColumn title="Brands" className="lg:col-span-2">
                    {site.brands.map((brand) => (
                        <FooterLink
                            key={brand.slug}
                            href={brandShow(brand.slug).url}
                        >
                            {brand.name}
                        </FooterLink>
                    ))}
                </FooterColumn>

                <FooterColumn title="Useful links" className="lg:col-span-2">
                    {NAV_ITEMS.slice(1).map((item) => (
                        <FooterLink key={item.href} href={item.href}>
                            {item.label}
                        </FooterLink>
                    ))}
                    <FooterLink href={privacy().url}>Privacy policy</FooterLink>
                </FooterColumn>

                <FooterColumn title="Contact" className="lg:col-span-3">
                    {phone && telHref && (
                        <li>
                            <a
                                href={telHref}
                                className="inline-flex items-start gap-2 hover:text-gold"
                            >
                                <Phone
                                    className="mt-0.5 size-4 shrink-0 text-gold"
                                    aria-hidden
                                />
                                {phone}
                            </a>
                        </li>
                    )}
                    {chat && (
                        <li>
                            <a
                                href={chat}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-start gap-2 hover:text-gold"
                            >
                                <BrandIcon
                                    name="whatsapp"
                                    className="mt-0.5 size-4 shrink-0 text-gold"
                                />
                                WhatsApp us
                            </a>
                        </li>
                    )}
                    {site.email && (
                        <li>
                            <a
                                href={`mailto:${site.email}`}
                                className="inline-flex items-start gap-2 [overflow-wrap:anywhere] hover:text-gold"
                            >
                                <Mail
                                    className="mt-0.5 size-4 shrink-0 text-gold"
                                    aria-hidden
                                />
                                {site.email}
                            </a>
                        </li>
                    )}
                    {site.address && (
                        <li className="flex items-start gap-2">
                            <MapPin
                                className="mt-0.5 size-4 shrink-0 text-gold"
                                aria-hidden
                            />
                            <address className="not-italic">
                                {site.address}
                            </address>
                        </li>
                    )}
                </FooterColumn>
            </div>

            {mapUrl && (
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <iframe
                        src={mapUrl}
                        title={`Map: ${site.address ?? site.company_name}`}
                        loading="lazy"
                        referrerPolicy="no-referrer-when-downgrade"
                        className="h-56 w-full rounded-md border border-white/10 grayscale hue-rotate-180 invert-[0.9]"
                    />
                </div>
            )}

            {site.payment_methods.length > 0 && (
                <div className="mx-auto mt-10 flex max-w-7xl flex-wrap items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <span className="mr-2 eyebrow">We accept</span>
                    {site.payment_methods.map((method) => (
                        <span
                            key={method}
                            className="rounded border border-white/10 px-3 py-1 text-xs text-white/80"
                        >
                            {method}
                        </span>
                    ))}
                </div>
            )}

            <div className="mt-10 border-t border-white/5">
                <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-xs text-white/50 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                    <p>
                        © {new Date().getFullYear()} {site.company_name}. All
                        rights reserved.
                    </p>
                    <ul className="flex gap-5">
                        <li>
                            <Link
                                href={rentalPolicy()}
                                className="hover:text-gold"
                            >
                                Rental policy
                            </Link>
                        </li>
                        <li>
                            <Link href={privacy()} className="hover:text-gold">
                                Privacy policy
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </footer>
    );
}

function FooterColumn({
    title,
    className,
    children,
}: {
    title: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={className}>
            <h2 className="eyebrow">{title}</h2>
            <ul className="mt-5 space-y-3">{children}</ul>
        </div>
    );
}

function FooterLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <li>
            <Link href={href} className="transition-colors hover:text-gold">
                {children}
            </Link>
        </li>
    );
}
