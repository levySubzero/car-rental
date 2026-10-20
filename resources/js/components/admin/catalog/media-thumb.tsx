import { ImageOff } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { MediaItem } from '@/types';

/**
 * A small square preview for lists: a logo or a car's cover photo.
 */
export function MediaThumb({
    media,
    fit = 'cover',
    className,
}: {
    media: MediaItem | null | undefined;
    fit?: 'cover' | 'contain';
    className?: string;
}) {
    const src = media?.urls.thumb ?? media?.urls.original;

    return (
        <div
            className={cn(
                'flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted',
                className,
            )}
        >
            {src ? (
                <img
                    src={src}
                    alt={media?.alt ?? ''}
                    loading="lazy"
                    className={cn(
                        'h-full w-full',
                        fit === 'cover' ? 'object-cover' : 'object-contain p-1',
                    )}
                />
            ) : (
                <ImageOff className="size-4 text-muted-foreground" />
            )}
        </div>
    );
}
