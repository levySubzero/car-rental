/*
 * schema.org JSON-LD for search engines (M7.17): the business on the home and contact
 * pages, and each car on its own page.
 */
import type { CarDetail, SharedSite } from '@/types';

type JsonLd = Record<string, unknown>;

export function businessJsonLd(site: SharedSite): JsonLd {
    return {
        '@context': 'https://schema.org',
        '@type': 'AutoRental',
        name: site.company_name,
        url: site.url || undefined,
        image: site.share_image ?? undefined,
        telephone: site.phones[0] ?? undefined,
        email: site.email ?? undefined,
        address: site.address
            ? {
                  '@type': 'PostalAddress',
                  streetAddress: site.address,
                  addressLocality: 'Nairobi',
                  addressCountry: 'KE',
              }
            : undefined,
        geo:
            site.latitude !== null && site.longitude !== null
                ? {
                      '@type': 'GeoCoordinates',
                      latitude: site.latitude,
                      longitude: site.longitude,
                  }
                : undefined,
        areaServed: 'Kenya',
        sameAs: Object.values(site.socials),
    };
}

export function carJsonLd(
    car: CarDetail,
    site: SharedSite,
    url: string,
): JsonLd {
    const images = car.gallery
        .map((photo) => photo.urls.full ?? photo.urls.original)
        .filter((src): src is string => Boolean(src))
        .map((src) => (src.startsWith('http') ? src : `${site.url}${src}`));

    return {
        '@context': 'https://schema.org',
        '@type': 'Car',
        name: car.name,
        url,
        image: images,
        brand: car.brand
            ? { '@type': 'Brand', name: car.brand.name }
            : undefined,
        vehicleTransmission: car.transmission,
        fuelType: car.fuel_type,
        seatingCapacity: car.seats,
        numberOfDoors: car.doors,
        color: car.colour ?? undefined,
        vehicleModelDate: car.model_year ? String(car.model_year) : undefined,
        offers: {
            '@type': 'Offer',
            url,
            price: car.daily_rate.major,
            priceCurrency: car.daily_rate.currency,
            availability: car.is_available
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            priceSpecification: {
                '@type': 'UnitPriceSpecification',
                price: car.daily_rate.major,
                priceCurrency: car.daily_rate.currency,
                unitCode: 'DAY',
            },
            seller: { '@type': 'AutoRental', name: site.company_name },
        },
    };
}

/**
 * JSON for an inline script: "<" is escaped so content can never close the tag.
 */
export function jsonLdScript(data: JsonLd): string {
    return JSON.stringify(data).replace(/</g, '\\u003c');
}
