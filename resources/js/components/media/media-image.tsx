import type { ComponentProps } from 'react';
import type { MediaConversion, MediaItem } from '@/types';

const WIDTHS: [MediaConversion, number][] = [
    ['thumb', 320],
    ['card', 800],
    ['full', 1920],
];

/**
 * A public image with a responsive srcset built from its conversions. Until the
 * conversions are ready it shows the original.
 *
 *     <MediaImage media={car.cover} conversion="card" sizes="(min-width: 768px) 33vw, 100vw" />
 */
export function MediaImage({
    media,
    conversion = 'card',
    sizes = '100vw',
    alt,
    loading = 'lazy',
    ...img
}: Omit<ComponentProps<'img'>, 'src' | 'srcSet'> & {
    media: MediaItem;
    conversion?: MediaConversion;
}) {
    const { urls } = media;

    // Conversions never upscale, so a small original caps every width.
    const srcSet =
        media.processing === 'done'
            ? WIDTHS.filter(([name]) => urls[name])
                  .map(
                      ([name, width]) =>
                          `${urls[name]} ${Math.min(width, media.width ?? width)}w`,
                  )
                  .join(', ')
            : undefined;

    return (
        <img
            src={urls[conversion] ?? urls.original}
            srcSet={srcSet}
            sizes={srcSet ? sizes : undefined}
            alt={alt ?? media.alt ?? ''}
            width={media.width ?? undefined}
            height={media.height ?? undefined}
            loading={loading}
            decoding="async"
            {...img}
        />
    );
}
