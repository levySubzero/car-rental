import { usePage } from '@inertiajs/react';
import type { SharedCurrency } from '@/lib/money';
import { whatsappLink } from '@/lib/whatsapp';
import type { SharedSite } from '@/types';

const EMPTY_SITE: SharedSite = {
    company_name: 'Baines Executives',
    url: '',
    share_image: null,
    latitude: null,
    longitude: null,
    phones: [],
    whatsapp_number: null,
    email: null,
    address: null,
    map_embed_url: null,
    socials: {},
    whatsapp_templates: {},
    car_types: [],
    brands: [],
    payment_methods: [],
};

const EMPTY_CURRENCY: SharedCurrency = { selected: 'KES', options: [] };

/**
 * The public site's shared props (ShareSite, ShareCurrency) with contact helpers.
 */
export function useSite() {
    const { site = EMPTY_SITE, currency = EMPTY_CURRENCY } = usePage().props;
    const phone = site.phones[0] ?? null;

    return {
        site,
        currency,
        phone,
        // "tel:+254724422051"
        telHref: phone ? `tel:${phone.replace(/[^\d+]/g, '')}` : null,
        whatsapp: (car?: string) =>
            whatsappLink(
                site.whatsapp_number,
                site.whatsapp_templates[
                    car ? 'car_enquiry' : 'general_enquiry'
                ],
                { car },
            ),
    };
}
