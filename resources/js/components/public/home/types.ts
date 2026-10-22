import type {
    CarCard,
    PublicListItem,
    MediaItem,
    MediaUrls,
    PublicBrand,
    PublicCarType,
    PublicPolicy,
    PublicService,
} from '@/types';

// Mirrors App\Http\Controllers\Public\HomeController.
export type HomeSectionKey =
    | 'about'
    | 'featured_fleet'
    | 'car_types'
    | 'services'
    | 'policy_highlights'
    | 'requirements'
    | 'why_choose_us'
    | 'trust_strip'
    | 'contact_cta';

// The `home` preset page (config/pages.php) as PageResource sends it.
export type HomeContent = {
    about_heading: string | null;
    about_body_html: string;
    about_image: { urls: MediaUrls; alt: string | null } | null;
    requirements: PublicListItem[];
    why_choose_us: PublicListItem[];
    contact_heading: string | null;
    contact_body: string | null;
};

export type TrustFigure = { value: string; label: string };

export type HomeProps = {
    hero: {
        media: MediaItem | null;
        // Optional loop over the image (site.hero_video).
        video: MediaItem | null;
        headline: string;
        subline: string;
        primary_cta_label: string;
        secondary_cta_label: string;
    };
    sections: HomeSectionKey[];
    marquee_position: 'below_hero' | 'page_bottom';
    trust_figures: TrustFigure[];
    content: HomeContent | null;
    featured: CarCard[];
    car_type_rows: { type: PublicCarType; cars: CarCard[] }[];
    services: PublicService[];
    policies: PublicPolicy[];
    brands: PublicBrand[];
};
