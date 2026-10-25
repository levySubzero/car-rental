import { describe, expect, it } from 'vite-plus/test';
import type { CarDetail, SharedSite } from '@/types';
import { businessJsonLd, carJsonLd, jsonLdScript } from '../structured-data';

// M12.9: the JSON-LD Google reads for rich results.
const site: SharedSite = {
    company_name: 'Baines Executives',
    url: 'https://baines.example',
    share_image: 'https://baines.example/media-files/hero.webp',
    latitude: -1.2921,
    longitude: 36.8219,
    phones: ['+254 700 000 000'],
    whatsapp_number: null,
    email: 'hello@baines.example',
    address: 'Westlands, Nairobi',
    map_embed_url: null,
    socials: { instagram: 'https://instagram.com/baines' },
    whatsapp_templates: {},
    car_types: [],
    brands: [],
    payment_methods: [],
};

const car = {
    name: 'Mazda Demio',
    gallery: [
        {
            urls: {
                full: '/media-files/a.webp',
                original: '/media-files/a.jpg',
            },
        },
        { urls: { full: null, original: 'https://cdn.example/b.jpg' } },
    ],
    brand: { name: 'Mazda' },
    transmission: 'Automatic',
    fuel_type: 'Petrol',
    seats: 5,
    doors: 4,
    colour: null,
    model_year: 2019,
    daily_rate: { major: '6000.00', currency: 'KES' },
    is_available: true,
} as unknown as CarDetail;

describe('businessJsonLd', () => {
    it('describes the business as an AutoRental with its address and position', () => {
        const data = businessJsonLd(site);

        expect(data).toMatchObject({
            '@context': 'https://schema.org',
            '@type': 'AutoRental',
            name: 'Baines Executives',
            telephone: '+254 700 000 000',
            address: { '@type': 'PostalAddress', addressCountry: 'KE' },
            geo: { latitude: -1.2921, longitude: 36.8219 },
            sameAs: ['https://instagram.com/baines'],
        });
    });

    it('leaves out what the site does not have', () => {
        const data = businessJsonLd({
            ...site,
            address: null,
            latitude: null,
            phones: [],
        });

        expect(data.address).toBeUndefined();
        expect(data.geo).toBeUndefined();
        expect(data.telephone).toBeUndefined();
    });
});

describe('carJsonLd', () => {
    it('gives absolute image URLs and a daily price offer', () => {
        const data = carJsonLd(
            car,
            site,
            'https://baines.example/cars/mazda-demio',
        );

        expect(data.image).toEqual([
            'https://baines.example/media-files/a.webp',
            'https://cdn.example/b.jpg',
        ]);
        expect(data.offers).toMatchObject({
            price: '6000.00',
            priceCurrency: 'KES',
            availability: 'https://schema.org/InStock',
            priceSpecification: { unitCode: 'DAY' },
        });
        expect(data.vehicleModelDate).toBe('2019');
    });
});

describe('jsonLdScript', () => {
    it('cannot close the script tag it is written into', () => {
        expect(
            jsonLdScript({ name: '</script><script>alert(1)</script>' }),
        ).not.toContain('</script>');
    });
});
