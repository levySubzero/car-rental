import { Link } from '@inertiajs/react';
import AutoScroll from 'embla-carousel-auto-scroll';
import useEmblaCarousel from 'embla-carousel-react';
import { useMemo } from 'react';
import { show } from '@/routes/car-brands';
import type { PublicBrand } from '@/types';

function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

/**
 * Brand logo strip (M7.7): scrolls on its own, pauses on hover or focus, and stands
 * still for renters who prefer reduced motion. Each brand links to its fleet page.
 */
export function BrandMarquee({ brands }: { brands: PublicBrand[] }) {
    // A short list is repeated so the loop never shows a gap on wide screens.
    const items = useMemo(() => {
        if (brands.length === 0) {
            return [];
        }

        const copies = Math.max(1, Math.ceil(12 / brands.length));

        return Array.from({ length: copies }, (_, copy) =>
            brands.map((brand) => ({
                brand,
                key: `${copy}-${brand.slug}`,
                copy,
            })),
        ).flat();
    }, [brands]);

    const plugins = useMemo(
        () =>
            prefersReducedMotion()
                ? []
                : [
                      AutoScroll({
                          speed: 0.6,
                          startDelay: 0,
                          stopOnInteraction: false,
                          stopOnMouseEnter: true,
                          stopOnFocusIn: true,
                      }),
                  ],
        [],
    );

    const [viewport] = useEmblaCarousel(
        { loop: true, dragFree: true, align: 'start' },
        plugins,
    );

    if (items.length === 0) {
        return null;
    }

    return (
        <section
            aria-label="Our brands"
            className="border-y border-white/5 bg-surface py-10"
        >
            <div
                ref={viewport}
                className="overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]"
            >
                <ul className="flex touch-pan-y">
                    {items.map(({ brand, key, copy }) => (
                        <li
                            key={key}
                            className="min-w-0 shrink-0 basis-1/3 px-4 sm:basis-1/5 lg:basis-1/7"
                        >
                            <Link
                                href={show(brand.slug)}
                                // Repeats are for the loop only: keep them out of the tab order and screen readers.
                                tabIndex={copy > 0 ? -1 : undefined}
                                aria-hidden={copy > 0 ? true : undefined}
                                className="flex h-16 items-center justify-center rounded opacity-70 grayscale transition hover:opacity-100 hover:grayscale-0 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none"
                            >
                                {brand.logo?.thumb ? (
                                    <img
                                        src={brand.logo.thumb}
                                        alt={brand.name}
                                        loading="lazy"
                                        className="max-h-12 w-auto max-w-full object-contain"
                                    />
                                ) : (
                                    <span className="font-display text-lg tracking-[0.15em] whitespace-nowrap text-white uppercase">
                                        {brand.name}
                                    </span>
                                )}
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
