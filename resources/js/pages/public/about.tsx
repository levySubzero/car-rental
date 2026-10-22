import {
    ContactCta,
    TrustStrip,
    WhyChooseUs,
} from '@/components/public/home/content-sections';
import type { TrustFigure } from '@/components/public/home/types';
import { PageHeader } from '@/components/public/page-header';
import { Seo } from '@/components/public/seo';
import type { PageImage, PublicListItem } from '@/types';

/**
 * The About page (M7.13), from the About preset page. Until it has its own story the home
 * page's About copy is shown (PageController).
 */
export default function About({
    heading,
    intro,
    header_image: headerImage,
    body_html: body,
    image,
    reasons,
    trust_figures: figures,
}: {
    heading: string;
    intro: string | null;
    header_image: PageImage | null;
    body_html: string;
    image: PageImage | null;
    reasons: PublicListItem[];
    trust_figures: TrustFigure[];
}) {
    return (
        <>
            <Seo
                title={heading}
                description={
                    intro ??
                    'Baines Executives: executive car rental, chauffeurs and safaris from Kilimani, Nairobi, since 2023.'
                }
                image={image?.urls.full ?? null}
            />
            <PageHeader title={heading} intro={intro} image={headerImage} />

            <section
                aria-label="Our story"
                className="mx-auto grid max-w-7xl items-start gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8"
            >
                <div
                    className="prose max-w-none prose-invert prose-headings:font-semibold prose-headings:text-white prose-p:leading-relaxed prose-p:text-white/75 prose-a:text-gold"
                    // Rendered from Markdown on the server (D23).
                    dangerouslySetInnerHTML={{ __html: body }}
                />
                {image?.urls.card ? (
                    <img
                        src={image.urls.card}
                        srcSet={[
                            `${image.urls.card} 800w`,
                            image.urls.full && `${image.urls.full} 1920w`,
                        ]
                            .filter(Boolean)
                            .join(', ')}
                        sizes="(min-width: 1024px) 50vw, 100vw"
                        alt={image.alt ?? ''}
                        className="aspect-4/3 w-full rounded-lg object-cover"
                    />
                ) : (
                    <div
                        aria-hidden
                        className="flex aspect-4/3 w-full flex-col justify-end rounded-lg border border-white/10 bg-surface bg-[radial-gradient(ellipse_at_top_left,rgb(200_164_90/0.18),transparent_60%)] p-10"
                    >
                        <p className="text-sm text-white/60">
                            Since 2023, from Kilimani, Nairobi
                        </p>
                        <p className="mt-4 font-display text-4xl leading-tight text-white/90">
                            Executive cars.
                            <br />
                            Chauffeurs.
                            <br />
                            Safaris.
                        </p>
                    </div>
                )}
            </section>

            <div className="tone-olive">
                <WhyChooseUs items={reasons} />
            </div>
            <div className="tone-gold">
                <TrustStrip figures={figures} />
            </div>
            <ContactCta content={null} />
        </>
    );
}
