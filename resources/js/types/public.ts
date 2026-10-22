import type { Money } from './admin';
import type { MediaConversion, MediaItem } from './media';

/*
 * Public site props (M7). Each type mirrors a resource in app/Http/Resources/Public
 * or the `site` prop from App\Http\Middleware\ShareSite.
 */

export type MediaUrls = Partial<Record<'original' | MediaConversion, string>>;

// An image field of a preset page, as PageResource sends it.
export type PageImage = { urls: MediaUrls; alt: string | null };

export type PublicBrand = {
    name: string;
    slug: string;
    logo?: MediaUrls | null;
};

export type PublicCarType = {
    name: string;
    slug: string;
    description: string | null;
};

export type CarCard = {
    slug: string;
    name: string;
    brand?: PublicBrand;
    car_type?: PublicCarType;
    cover?: MediaItem | null;
    daily_rate: Money;
    engine_type: string | null;
    engine_capacity_litres: string | null;
    fuel_type: string;
    transmission: string;
    doors: number;
    seats: number;
    mileage_limit_km: number;
    is_available: boolean;
};

// CarDetailResource: the card fields plus the car page's own (PRD F-3, F-4).
export type CarDetail = CarCard & {
    model_year: number | null;
    colour: string | null;
    description: string | null;
    damage_excess: Money | null;
    gallery: MediaItem[];
};

export type PublicService = {
    title: string;
    slug: string;
    summary: string | null;
    body_html: string;
    cta: { type: string; value: string | null };
    image?: MediaUrls | null;
};

export type PublicPolicy = {
    title: string;
    slug: string;
    group: string;
    summary: string | null;
    version: number | null;
    body_html: string;
};

// A `list` content field item with the `title` and `body` subfields.
export type PublicListItem = { title: string; body: string };

export type SharedSite = {
    company_name: string;
    url: string;
    share_image: string | null;
    latitude: number | null;
    longitude: number | null;
    phones: string[];
    whatsapp_number: string | null;
    email: string | null;
    address: string | null;
    map_embed_url: string | null;
    // Platform => URL
    socials: Record<string, string>;
    whatsapp_templates: Record<string, string>;
    car_types: { name: string; slug: string }[];
    brands: { name: string; slug: string }[];
    payment_methods: string[];
};
