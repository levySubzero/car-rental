import { Mail, Phone } from 'lucide-react';
import { CurrencySwitcher } from '@/components/public/currency-switcher';
import { SocialLinks } from '@/components/public/social-links';
import { useSite } from '@/hooks/use-site';

export function TopBar() {
    const { site, phone, telHref } = useSite();

    return (
        <div className="border-b border-white/5 bg-ink text-xs text-white/70">
            <div className="mx-auto flex h-9 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <div className="flex items-center gap-5">
                    {phone && telHref && (
                        <a
                            href={telHref}
                            className="inline-flex items-center gap-1.5 transition-colors hover:text-gold"
                        >
                            <Phone className="size-3.5 text-gold" aria-hidden />
                            {phone}
                        </a>
                    )}
                    {site.email && (
                        <a
                            href={`mailto:${site.email}`}
                            className="hidden items-center gap-1.5 transition-colors hover:text-gold sm:inline-flex"
                        >
                            <Mail className="size-3.5 text-gold" aria-hidden />
                            {site.email}
                        </a>
                    )}
                </div>
                <div className="flex items-center gap-4">
                    <SocialLinks
                        className="hidden sm:flex"
                        iconClassName="size-3.5"
                    />
                    <CurrencySwitcher />
                </div>
            </div>
        </div>
    );
}
