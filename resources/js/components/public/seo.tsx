import { Head, usePage } from '@inertiajs/react';
import { useSite } from '@/hooks/use-site';
import { jsonLdScript } from '@/lib/structured-data';

/**
 * Title, description, canonical URL and link previews for a public page (M7.17). The
 * canonical URL drops the query string, so filtered fleet views point to their page.
 */
export function Seo({
    title,
    description,
    image,
    jsonLd,
}: {
    title: string;
    description?: string | null;
    image?: string | null;
    jsonLd?: Record<string, unknown>;
}) {
    const { site } = useSite();
    const { url } = usePage();
    const canonical = `${site.url}${url.split('?')[0]}`;
    const shareImage = image ?? site.share_image;
    const fullTitle = `${title} - ${site.company_name}`;

    return (
        <Head title={title}>
            {description && (
                <meta
                    head-key="description"
                    name="description"
                    content={description}
                />
            )}
            <link head-key="canonical" rel="canonical" href={canonical} />
            <meta head-key="og:type" property="og:type" content="website" />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={site.company_name}
            />
            <meta head-key="og:title" property="og:title" content={fullTitle} />
            {description && (
                <meta
                    head-key="og:description"
                    property="og:description"
                    content={description}
                />
            )}
            <meta head-key="og:url" property="og:url" content={canonical} />
            {shareImage && (
                <meta
                    head-key="og:image"
                    property="og:image"
                    content={shareImage}
                />
            )}
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content={shareImage ? 'summary_large_image' : 'summary'}
            />
            {jsonLd && (
                <script
                    head-key="json-ld"
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{ __html: jsonLdScript(jsonLd) }}
                />
            )}
        </Head>
    );
}
