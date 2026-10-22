import { Link } from '@inertiajs/react';
import { BrandIcon } from '@/components/public/icons';
import { MediaImage } from '@/components/media/media-image';
import { Button } from '@/components/ui/button';
import { useSite } from '@/hooks/use-site';
import { HeroVideo } from './hero-video';
import { fleet } from '@/routes';
import type { HomeProps } from './types';

/**
 * Full-bleed hero (M7.5): the hero image with a slow zoom (the page's one ambient motion),
 * or a gold-lit gradient until the client's photo or video arrives.
 */
export function Hero({ hero }: { hero: HomeProps['hero'] }) {
    const video = hero.video;
    const { whatsapp } = useSite();
    const enquire = whatsapp();

    return (
        <section className="relative isolate -mt-18 flex min-h-[calc(100svh-2.25rem)] items-end overflow-hidden pt-18">
            <div aria-hidden className="absolute inset-0 -z-10">
                {hero.media ? (
                    <MediaImage
                        media={hero.media}
                        conversion="full"
                        sizes="100vw"
                        loading="eager"
                        fetchPriority="high"
                        alt=""
                        className="size-full object-cover motion-safe:animate-hero-zoom"
                    />
                ) : (
                    <div className="size-full bg-ink bg-[radial-gradient(ellipse_80%_60%_at_75%_20%,rgb(200_164_90/0.22),transparent_60%),radial-gradient(ellipse_60%_50%_at_10%_90%,rgb(200_164_90/0.10),transparent_60%)]" />
                )}
                {video?.urls.original && (
                    <HeroVideo
                        src={video.urls.original}
                        type={video.mime}
                        poster={hero.media?.urls.full}
                    />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-ink via-ink/60 to-ink/30" />
                <div className="absolute inset-0 bg-gradient-to-r from-ink/80 via-transparent to-transparent" />
            </div>

            <div className="mx-auto w-full max-w-7xl px-4 pb-20 sm:px-6 sm:pb-28 lg:px-8">
                <div className="max-w-3xl motion-safe:animate-fade-up">
                    <h1 className="text-[2.75rem] leading-[1.04] font-semibold tracking-[-0.035em] text-balance text-white sm:text-6xl lg:text-7xl">
                        {hero.headline}
                    </h1>
                    <p className="mt-6 max-w-xl text-lg leading-relaxed text-white/75">
                        {hero.subline}
                    </p>
                    <div className="mt-10 flex flex-wrap gap-3">
                        <Button
                            asChild
                            size="lg"
                            className="h-12 px-7 text-base font-semibold"
                        >
                            <Link href={fleet()}>{hero.primary_cta_label}</Link>
                        </Button>
                        {enquire && (
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="h-12 border-white/25 bg-white/5 px-7 text-base text-white backdrop-blur hover:border-gold hover:bg-white/10 hover:text-white"
                            >
                                <a
                                    href={enquire}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <BrandIcon
                                        name="whatsapp"
                                        className="size-4"
                                    />
                                    {hero.secondary_cta_label}
                                </a>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
