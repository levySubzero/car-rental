import type { ReactNode } from 'react';
import { BrandMarquee } from '@/components/public/brand-marquee';
import {
    About,
    ContactCta,
    PolicyHighlights,
    Requirements,
    TrustStrip,
    WhyChooseUs,
} from '@/components/public/home/content-sections';
import {
    CarTypeRows,
    FeaturedFleet,
} from '@/components/public/home/fleet-sections';
import { Hero } from '@/components/public/home/hero';
import { ServicesCarousel } from '@/components/public/home/services-carousel';
import type { HomeProps, HomeSectionKey } from '@/components/public/home/types';
import { Seo } from '@/components/public/seo';
import { useSite } from '@/hooks/use-site';
import { businessJsonLd } from '@/lib/structured-data';

/*
 * Each section sits on its own tone (app.css) so the page alternates instead of running
 * black on black. Car type rows alternate tones themselves; the contact band paints its own.
 */
const TONES: Partial<Record<HomeSectionKey, string>> = {
    services: 'tone-charcoal',
    featured_fleet: 'tone-ink',
    why_choose_us: 'tone-olive',
    trust_strip: 'tone-gold',
    requirements: 'tone-ink',
    about: 'tone-sand',
    policy_highlights: 'tone-charcoal',
};

/**
 * The home page (M7.5–M7.7). Sections render in the order staff set in Home settings;
 * hidden ones are not sent at all.
 */
export default function Home(props: HomeProps) {
    const { hero, sections, content, trust_figures: figures } = props;
    const { site } = useSite();

    const render: Record<HomeSectionKey, () => ReactNode> = {
        about: () => <About content={content} />,
        featured_fleet: () => <FeaturedFleet cars={props.featured} />,
        car_types: () => <CarTypeRows rows={props.car_type_rows} />,
        services: () => <ServicesCarousel services={props.services} />,
        policy_highlights: () => <PolicyHighlights policies={props.policies} />,
        requirements: () => <Requirements content={content} />,
        why_choose_us: () => <WhyChooseUs items={content?.why_choose_us} />,
        trust_strip: () => <TrustStrip figures={figures} />,
        contact_cta: () => <ContactCta content={content} />,
    };

    const marquee = <BrandMarquee brands={props.brands} />;

    return (
        <>
            <Seo
                title="Executive car rental in Nairobi"
                description={hero.subline}
                jsonLd={businessJsonLd(site)}
            />
            <Hero hero={hero} />
            {props.marquee_position === 'below_hero' && marquee}
            <div id="home-content">
                {sections.map((key) => (
                    <div key={key} className={TONES[key]}>
                        {render[key]?.()}
                    </div>
                ))}
            </div>
            {props.marquee_position === 'page_bottom' && marquee}
        </>
    );
}
