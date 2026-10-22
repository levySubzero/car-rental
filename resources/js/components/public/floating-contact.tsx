import { Phone } from 'lucide-react';
import { BrandIcon } from '@/components/public/icons';
import { useSite } from '@/hooks/use-site';

/**
 * Always-visible WhatsApp and Call buttons (M7.3). A bar along the bottom on phones,
 * round buttons in the corner on larger screens.
 */
export function FloatingContact({ car }: { car?: string }) {
    const { telHref, whatsapp } = useSite();
    const chat = whatsapp(car);

    if (!chat && !telHref) {
        return null;
    }

    return (
        <>
            <div className="fixed inset-x-0 bottom-0 z-40 grid grid-cols-2 border-t border-white/10 bg-ink/95 backdrop-blur sm:hidden">
                {telHref && (
                    <a
                        href={telHref}
                        className="flex items-center justify-center gap-2 py-3.5 text-sm font-semibold text-white"
                    >
                        <Phone className="size-4 text-gold" aria-hidden />
                        Call us
                    </a>
                )}
                {chat && (
                    <a
                        href={chat}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center justify-center gap-2 bg-[#25D366] py-3.5 text-sm font-semibold text-ink"
                    >
                        <BrandIcon name="whatsapp" className="size-4" />
                        WhatsApp
                    </a>
                )}
            </div>

            <div className="fixed right-6 bottom-6 z-40 hidden flex-col gap-3 sm:flex">
                {telHref && (
                    <a
                        href={telHref}
                        aria-label="Call us"
                        className="flex size-13 items-center justify-center rounded-full border border-gold/40 bg-ink text-gold shadow-lg shadow-black/40 transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none"
                    >
                        <Phone className="size-5" />
                    </a>
                )}
                {chat && (
                    <a
                        href={chat}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Chat on WhatsApp (opens in a new tab)"
                        className="flex size-13 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-black/40 transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none"
                    >
                        <BrandIcon name="whatsapp" className="size-6" />
                    </a>
                )}
            </div>
        </>
    );
}
