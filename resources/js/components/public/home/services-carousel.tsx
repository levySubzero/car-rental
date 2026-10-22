import { Link } from '@inertiajs/react';
import AutoScroll from 'embla-carousel-auto-scroll';
import useEmblaCarousel from 'embla-carousel-react';
import { useMemo } from 'react';
import { SectionHeading } from '@/components/public/section-heading';
import { serviceIcon } from '@/components/public/service-icons';
import { Button } from '@/components/ui/button';
import { services as servicesPage } from '@/routes';
import type { PublicService } from '@/types';

function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

/**
 * The service portfolio as a continuously gliding row of photo cards, like the brand
 * strip: it stops while the pointer is over it or a card has focus, can be dragged or
 * swiped, and stands still for renters who prefer reduced motion.
 */
export function ServicesCarousel({ services }: { services: PublicService[] }) {
    // Loop needs more cards than fit on screen; a short list is repeated.
    const items = useMemo(() => {
        const copies = Math.max(1, Math.ceil(8 / Math.max(services.length, 1)));

        return Array.from({ length: copies }, (_, copy) =>
            services.map((service) => ({ service, copy })),
        ).flat();
    }, [services]);

    const plugins = useMemo(
        () =>
            prefersReducedMotion()
                ? []
                : [
                      AutoScroll({
                          speed: 0.7,
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

    if (services.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="services-heading" className="py-20 lg:py-24">
            <div className="mx-auto flex max-w-7xl flex-wrap items-end justify-between gap-6 px-4 sm:px-6 lg:px-8">
                <SectionHeading id="services-heading" title="Our services">
                    <p>
                        Self-drive or with a driver, from the airport run to a
                        week in the parks.
                    </p>
                </SectionHeading>
                <Button
                    asChild
                    variant="outline"
                    className="h-11 border-white/20 bg-transparent px-6 text-white hover:border-gold hover:bg-white/5 hover:text-white"
                >
                    <Link href={servicesPage()}>Explore all services</Link>
                </Button>
            </div>

            {/* Edge to edge, fading out at the sides, so the row reads as continuous. */}
            <div
                ref={viewport}
                className="mt-12 overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_6%,black_94%,transparent)]"
            >
                <ul className="flex touch-pan-y">
                    {items.map(({ service, copy }) => (
                        <li
                            key={`${copy}-${service.slug}`}
                            // Repeats exist for the loop only: one tab stop and one announcement per service.
                            aria-hidden={copy > 0 ? true : undefined}
                            className="min-w-0 shrink-0 basis-[78%] pl-5 sm:basis-[45%] lg:basis-[30%] xl:basis-[23%]"
                        >
                            <ServiceCard
                                service={service}
                                focusable={copy === 0}
                            />
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

function ServiceCard({
    service,
    focusable,
}: {
    service: PublicService;
    focusable: boolean;
}) {
    const Icon = serviceIcon(service.slug);
    const image = service.image;

    return (
        <Link
            href={`${servicesPage().url}#${service.slug}`}
            tabIndex={focusable ? undefined : -1}
            className="group relative isolate flex aspect-4/5 flex-col justify-end overflow-hidden rounded-lg bg-black focus-visible:ring-2 focus-visible:ring-gold focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
        >
            {image?.card ? (
                <img
                    src={image.card}
                    srcSet={[
                        image.thumb && `${image.thumb} 320w`,
                        `${image.card} 800w`,
                        image.full && `${image.full} 1920w`,
                    ]
                        .filter(Boolean)
                        .join(', ')}
                    sizes="(min-width: 1280px) 23vw, (min-width: 1024px) 30vw, (min-width: 640px) 45vw, 78vw"
                    alt=""
                    loading="lazy"
                    className="absolute inset-0 -z-20 size-full scale-[1.03] object-cover transition-transform duration-700 motion-safe:group-hover:scale-[1.08]"
                />
            ) : (
                // Until staff upload a photo (Content → Services): the service's mark on a lit panel.
                <div
                    aria-hidden
                    className="absolute inset-0 -z-20 flex items-start justify-center bg-[radial-gradient(ellipse_at_30%_15%,rgb(200_164_90/0.28),transparent_60%)] pt-[18%]"
                >
                    <Icon
                        className="size-28 text-gold/45 transition-transform duration-700 motion-safe:group-hover:scale-105"
                        strokeWidth={1}
                    />
                </div>
            )}

            {/*
             * The text sits on the photo itself: a blurred copy of the photo fades in toward the
             * bottom (masked, so there is no edge; enlarged, so its soft edges stay outside the
             * card) and a gradient darkens it enough for white text.
             */}
            {image?.card && (
                <img
                    src={image.thumb ?? image.card}
                    alt=""
                    aria-hidden
                    loading="lazy"
                    className="absolute inset-0 -z-10 size-full scale-125 [mask-image:linear-gradient(to_top,black_30%,transparent_70%)] object-cover blur-xl"
                />
            )}
            <div
                aria-hidden
                className="absolute inset-0 -z-10 bg-gradient-to-t from-black/90 via-black/55 via-45% to-transparent"
            />

            <div className="p-6">
                <h3 className="text-2xl font-semibold tracking-tight text-white">
                    {service.title}
                </h3>
                {service.summary && (
                    <p className="mt-2 leading-relaxed text-white/80">
                        {service.summary}
                    </p>
                )}
                <span className="mt-4 inline-block text-sm font-semibold text-gold group-hover:underline group-focus-visible:underline">
                    Learn more
                </span>
            </div>
        </Link>
    );
}
