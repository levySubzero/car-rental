import { PageHeader } from '@/components/public/page-header';
import { Seo } from '@/components/public/seo';
import { useSite } from '@/hooks/use-site';

/**
 * The privacy notice (M7.13), written by the client in Content → Pages → Privacy notice.
 */
export default function Privacy({
    heading,
    body_html: body,
}: {
    heading: string;
    body_html: string;
}) {
    const { site } = useSite();

    return (
        <>
            <Seo
                title={heading}
                description={`How ${site.company_name} collects, uses and protects your personal data.`}
            />
            <PageHeader title={heading} />
            <section className="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
                {body ? (
                    <div
                        className="prose max-w-none prose-invert prose-headings:font-semibold prose-headings:text-white prose-p:leading-relaxed prose-p:text-white/75 prose-a:text-gold prose-li:text-white/75"
                        // Rendered from Markdown on the server (D23).
                        dangerouslySetInnerHTML={{ __html: body }}
                    />
                ) : (
                    <p className="text-lg leading-relaxed text-muted-foreground">
                        Our privacy notice is being finalised.
                        {site.email && (
                            <>
                                {' '}
                                For any question about your personal data, email{' '}
                                <a
                                    href={`mailto:${site.email}`}
                                    className="text-gold underline underline-offset-4"
                                >
                                    {site.email}
                                </a>
                                .
                            </>
                        )}
                    </p>
                )}
            </section>
        </>
    );
}
