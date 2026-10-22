import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import type { PageImage } from '@/types';

/**
 * The band that opens every inner public page: optional breadcrumb, heading, intro, and
 * the page's header image (set in Content → Pages) behind it, darkened for the text.
 */
export function PageHeader({
    title,
    intro,
    crumbs = [],
    image,
    children,
}: {
    title: string;
    intro?: string | null;
    crumbs?: { label: string; href: string }[];
    image?: PageImage | null;
    children?: ReactNode;
}) {
    const src = image?.urls.full ?? image?.urls.card ?? image?.urls.original;

    return (
        <section className="tone-charcoal relative isolate overflow-hidden">
            {src && (
                <>
                    <img
                        src={src}
                        srcSet={
                            [
                                image?.urls.card && `${image.urls.card} 800w`,
                                image?.urls.full && `${image.urls.full} 1920w`,
                            ]
                                .filter(Boolean)
                                .join(', ') || undefined
                        }
                        sizes="100vw"
                        alt={image?.alt ?? ''}
                        fetchPriority="high"
                        className="absolute inset-0 -z-20 size-full object-cover"
                    />
                    <div
                        aria-hidden
                        className="absolute inset-0 -z-10 bg-gradient-to-r from-ink/90 via-ink/70 to-ink/35"
                    />
                    <div
                        aria-hidden
                        className="absolute inset-x-0 bottom-0 -z-10 h-1/2 bg-gradient-to-t from-ink/70 to-transparent"
                    />
                </>
            )}
            <div
                className={cn(
                    'mx-auto max-w-7xl px-4 pt-16 pb-12 sm:px-6 lg:px-8 lg:pt-20',
                    src &&
                        'flex min-h-[20rem] flex-col justify-end pt-24 pb-14 lg:min-h-[24rem]',
                )}
            >
                {crumbs.length > 0 && (
                    <nav
                        aria-label="Breadcrumb"
                        className="mb-4 text-sm text-muted-foreground"
                    >
                        <ol className="flex flex-wrap items-center gap-2">
                            {crumbs.map((crumb) => (
                                <li
                                    key={crumb.href}
                                    className="flex items-center gap-2"
                                >
                                    <Link
                                        href={crumb.href}
                                        className="hover:text-foreground"
                                    >
                                        {crumb.label}
                                    </Link>
                                    <span aria-hidden>/</span>
                                </li>
                            ))}
                            <li aria-current="page" className="text-foreground">
                                {title}
                            </li>
                        </ol>
                    </nav>
                )}
                <h1 className="font-display text-4xl leading-tight text-foreground sm:text-5xl">
                    {title}
                </h1>
                {intro && (
                    <p
                        className={cn(
                            'mt-4 max-w-2xl text-lg leading-relaxed',
                            src ? 'text-white/85' : 'text-muted-foreground',
                        )}
                    >
                        {intro}
                    </p>
                )}
                {children}
            </div>
        </section>
    );
}
