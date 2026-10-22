import { Link } from '@inertiajs/react';
import { Phone } from 'lucide-react';
import { BrandIcon } from '@/components/public/icons';
import { PageHeader } from '@/components/public/page-header';
import { Seo } from '@/components/public/seo';
import { serviceIcon } from '@/components/public/service-icons';
import { Button } from '@/components/ui/button';
import { useSite } from '@/hooks/use-site';
import { cn } from '@/lib/utils';
import { whatsappLink } from '@/lib/whatsapp';
import { fleet } from '@/routes';
import type { PageImage, PublicService } from '@/types';

/**
 * The Services page (M7.12). Each service is a section with its own anchor, which the
 * home page cards link to; photo and text alternate sides down the page.
 */
export default function Services({
    heading,
    intro,
    header_image: headerImage,
    services,
}: {
    heading: string;
    intro: string | null;
    header_image: PageImage | null;
    services: PublicService[];
}) {
    return (
        <>
            <Seo
                title={heading}
                description={
                    intro ??
                    'Self-drive and chauffeur-driven car rental, airport transfers, safaris, corporate transport and delivery to your door in Nairobi.'
                }
            />
            <PageHeader title={heading} intro={intro} image={headerImage}>
                {services.length > 1 && (
                    <nav aria-label="Services on this page" className="mt-8">
                        <ul className="flex flex-wrap gap-2">
                            {services.map((service) => (
                                <li key={service.slug}>
                                    <a
                                        href={`#${service.slug}`}
                                        className="block rounded-full border border-white/15 px-4 py-2 text-sm text-white/80 transition-colors hover:border-gold hover:text-gold"
                                    >
                                        {service.title}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </nav>
                )}
            </PageHeader>

            {services.length === 0 ? (
                <p className="mx-auto max-w-7xl px-4 py-20 text-muted-foreground sm:px-6 lg:px-8">
                    Our services are being updated. Message us on WhatsApp and
                    we will help.
                </p>
            ) : (
                <div>
                    {services.map((service, index) => (
                        <ServiceSection
                            key={service.slug}
                            service={service}
                            flipped={index % 2 === 1}
                        />
                    ))}
                </div>
            )}
        </>
    );
}

function ServiceSection({
    service,
    flipped,
}: {
    service: PublicService;
    flipped: boolean;
}) {
    const Icon = serviceIcon(service.slug);
    const image = service.image;

    return (
        <section
            id={service.slug}
            aria-labelledby={`${service.slug}-title`}
            className={cn(
                'scroll-mt-28',
                flipped ? 'tone-charcoal' : 'tone-ink',
            )}
        >
            <div className="mx-auto grid max-w-7xl items-center gap-10 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
                <div
                    className={cn(
                        'mx-auto w-full max-w-md overflow-hidden rounded-lg bg-surface-raised',
                        flipped && 'lg:order-2',
                    )}
                >
                    {image?.card ? (
                        <img
                            src={image.card}
                            srcSet={[
                                `${image.card} 800w`,
                                image.full && `${image.full} 1920w`,
                            ]
                                .filter(Boolean)
                                .join(', ')}
                            sizes="(min-width: 1024px) 28rem, 100vw"
                            alt=""
                            loading="lazy"
                            className="aspect-4/5 w-full object-cover"
                        />
                    ) : (
                        <div
                            aria-hidden
                            className="flex aspect-4/5 items-center justify-center bg-[radial-gradient(ellipse_at_30%_20%,rgb(200_164_90/0.24),transparent_60%)]"
                        >
                            <Icon
                                className="size-28 text-gold/45"
                                strokeWidth={1}
                            />
                        </div>
                    )}
                </div>

                <div className="min-w-0">
                    <h2
                        id={`${service.slug}-title`}
                        className="font-display text-3xl leading-tight text-foreground sm:text-4xl"
                    >
                        {service.title}
                    </h2>
                    {service.summary && (
                        <p className="mt-4 text-lg leading-relaxed text-muted-foreground">
                            {service.summary}
                        </p>
                    )}
                    {service.body_html && (
                        <div
                            className="prose mt-6 max-w-none prose-invert prose-p:leading-relaxed prose-p:text-white/70 prose-a:text-gold prose-li:text-white/70"
                            // Rendered from Markdown on the server (D23).
                            dangerouslySetInnerHTML={{
                                __html: service.body_html,
                            }}
                        />
                    )}
                    <ServiceActions service={service} />
                </div>
            </div>
        </section>
    );
}

function ServiceActions({ service }: { service: PublicService }) {
    const { site, telHref, phone } = useSite();
    const { type, value } = service.cta;
    const enquiry = whatsappLink(
        site.whatsapp_number,
        value ||
            `Hi Baines Executives, I'd like to know more about ${service.title.toLowerCase()}.`,
    );

    const primary =
        type === 'book' ? (
            <Button asChild size="lg" className="h-12 px-7 font-semibold">
                <Link href={fleet()}>Browse the fleet</Link>
            </Button>
        ) : type === 'link' && value ? (
            <Button asChild size="lg" className="h-12 px-7 font-semibold">
                <a href={value}>Find out more</a>
            </Button>
        ) : enquiry ? (
            <Button
                asChild
                size="lg"
                className="h-12 bg-[#25D366] px-7 font-semibold text-ink hover:bg-[#25D366]/90"
            >
                <a href={enquiry} target="_blank" rel="noopener noreferrer">
                    <BrandIcon name="whatsapp" className="size-4" />
                    Enquire on WhatsApp
                </a>
            </Button>
        ) : null;

    return (
        <div className="mt-8 flex flex-wrap gap-3">
            {primary}
            {telHref && (
                <Button
                    asChild
                    size="lg"
                    variant="outline"
                    className="h-12 border-white/20 bg-transparent px-6 text-white hover:border-gold hover:bg-white/5 hover:text-white"
                >
                    <a href={telHref} aria-label={`Call ${phone}`}>
                        <Phone className="size-4" />
                        Call us
                    </a>
                </Button>
            )}
        </div>
    );
}
