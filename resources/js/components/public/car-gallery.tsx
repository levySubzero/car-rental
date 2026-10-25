import * as DialogPrimitive from '@radix-ui/react-dialog';
import useEmblaCarousel from 'embla-carousel-react';
import { ChevronLeft, ChevronRight, Expand, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { MediaImage } from '@/components/media/media-image';
import { cn } from '@/lib/utils';
import type { MediaItem } from '@/types';

/**
 * A car's photos (M7.11, PRD F-3): swipe or use the arrows and thumbnails; open any photo
 * full screen, where the arrow keys step through them and Esc closes.
 */
export function CarGallery({
    photos,
    name,
}: {
    photos: MediaItem[];
    name: string;
}) {
    const [viewport, api] = useEmblaCarousel({ loop: photos.length > 1 });
    const [index, setIndex] = useState(0);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!api) {
            return;
        }

        const select = () => setIndex(api.selectedScrollSnap());
        api.on('select', select).on('reInit', select);

        return () => {
            api.off('select', select).off('reInit', select);
        };
    }, [api]);

    const keys = (event: KeyboardEvent) => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            api?.scrollPrev();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            api?.scrollNext();
        }
    };

    if (photos.length === 0) {
        return null;
    }

    const multiple = photos.length > 1;

    return (
        <div className="grid gap-3">
            <div
                className="relative overflow-hidden rounded-lg bg-surface"
                role="region"
                aria-roledescription="carousel"
                aria-label={`Photos of the ${name}`}
                tabIndex={0}
                onKeyDown={keys}
            >
                <div ref={viewport} className="overflow-hidden">
                    {/* Slides are groups (APG carousel), so divs: an li with a role is no list item. */}
                    <div className="flex touch-pan-y">
                        {photos.map((photo, i) => (
                            <div
                                key={photo.uuid}
                                className="relative min-w-0 shrink-0 basis-full"
                                role="group"
                                aria-roledescription="slide"
                                aria-label={`${i + 1} of ${photos.length}`}
                            >
                                <button
                                    type="button"
                                    onClick={() => setOpen(true)}
                                    className="block w-full cursor-zoom-in focus-visible:outline-none"
                                    aria-label={`Open photo ${i + 1} full screen`}
                                    tabIndex={i === index ? 0 : -1}
                                >
                                    <MediaImage
                                        media={photo}
                                        conversion="full"
                                        sizes="(min-width: 1024px) 58vw, 100vw"
                                        loading={i === 0 ? 'eager' : 'lazy'}
                                        fetchPriority={
                                            i === 0 ? 'high' : undefined
                                        }
                                        alt={
                                            photo.alt ??
                                            `${name}, photo ${i + 1}`
                                        }
                                        className="aspect-4/3 w-full object-cover"
                                    />
                                </button>
                            </div>
                        ))}
                    </div>
                </div>

                <span className="pointer-events-none absolute top-3 left-3 rounded bg-ink/80 px-2.5 py-1 text-xs font-semibold text-white tabular-nums backdrop-blur">
                    {index + 1} / {photos.length}
                </span>
                <Expand
                    aria-hidden
                    className="pointer-events-none absolute top-3 right-3 size-5 text-white/80"
                />

                {multiple && (
                    <>
                        <ArrowButton
                            side="left"
                            label="Previous photo"
                            onClick={() => api?.scrollPrev()}
                        />
                        <ArrowButton
                            side="right"
                            label="Next photo"
                            onClick={() => api?.scrollNext()}
                        />
                    </>
                )}
            </div>

            {multiple && (
                <ul
                    className="grid grid-cols-6 gap-2"
                    aria-label="Choose a photo"
                >
                    {photos.map((photo, i) => (
                        <li key={photo.uuid}>
                            <button
                                type="button"
                                onClick={() => api?.scrollTo(i)}
                                aria-label={`Show photo ${i + 1}`}
                                aria-current={i === index ? 'true' : undefined}
                                className={cn(
                                    'block overflow-hidden rounded-md border-2 transition-colors focus-visible:border-gold focus-visible:outline-none',
                                    i === index
                                        ? 'border-gold'
                                        : 'border-transparent opacity-70 hover:opacity-100',
                                )}
                            >
                                <MediaImage
                                    media={photo}
                                    conversion="thumb"
                                    sizes="120px"
                                    alt=""
                                    className="aspect-4/3 w-full object-cover"
                                />
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <Lightbox
                open={open}
                onOpenChange={setOpen}
                photos={photos}
                name={name}
                start={index}
                onIndexChange={(i) => api?.scrollTo(i, true)}
            />
        </div>
    );
}

function ArrowButton({
    side,
    label,
    onClick,
    large = false,
}: {
    side: 'left' | 'right';
    label: string;
    onClick: () => void;
    large?: boolean;
}) {
    const Icon = side === 'left' ? ChevronLeft : ChevronRight;

    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className={cn(
                'absolute top-1/2 flex -translate-y-1/2 items-center justify-center rounded-full bg-ink/70 text-white backdrop-blur transition-colors hover:bg-ink focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none',
                large ? 'size-12' : 'size-10',
                side === 'left' ? 'left-3' : 'right-3',
            )}
        >
            <Icon className="size-5" />
        </button>
    );
}

function Lightbox({
    open,
    onOpenChange,
    photos,
    name,
    start,
    onIndexChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    photos: MediaItem[];
    name: string;
    start: number;
    onIndexChange: (index: number) => void;
}) {
    const [viewport, api] = useEmblaCarousel({
        loop: photos.length > 1,
        startIndex: start,
    });
    const [index, setIndex] = useState(start);

    useEffect(() => {
        if (!api) {
            return;
        }

        const select = () => {
            const i = api.selectedScrollSnap();
            setIndex(i);
            onIndexChange(i);
        };
        // Read the position once on open too: no select event fires for the start photo.
        select();
        api.on('select', select);

        return () => {
            api.off('select', select);
        };
    }, [api, onIndexChange]);

    const keys = useCallback(
        (event: KeyboardEvent) => {
            if (event.key === 'ArrowLeft') {
                api?.scrollPrev();
            } else if (event.key === 'ArrowRight') {
                api?.scrollNext();
            }
        },
        [api],
    );

    return (
        <DialogPrimitive.Root open={open} onOpenChange={onOpenChange}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Overlay className="public dark fixed inset-0 z-50 bg-black/95" />
                <DialogPrimitive.Content
                    className="public dark fixed inset-0 z-50 flex flex-col focus:outline-none"
                    onKeyDown={keys}
                    aria-describedby={undefined}
                >
                    <div className="flex items-center justify-between px-4 py-3 text-white sm:px-6">
                        <DialogPrimitive.Title className="text-sm font-semibold">
                            {name}
                            <span className="ml-3 font-normal text-white/60 tabular-nums">
                                {index + 1} / {photos.length}
                            </span>
                        </DialogPrimitive.Title>
                        <DialogPrimitive.Close
                            className="flex size-11 items-center justify-center rounded-full text-white/80 hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-gold focus-visible:outline-none"
                            aria-label="Close"
                        >
                            <X className="size-6" />
                        </DialogPrimitive.Close>
                    </div>
                    <div className="relative min-h-0 flex-1">
                        <div ref={viewport} className="h-full overflow-hidden">
                            <ul className="flex h-full touch-pan-y">
                                {photos.map((photo, i) => (
                                    <li
                                        key={photo.uuid}
                                        className="flex h-full min-w-0 shrink-0 basis-full items-center justify-center px-2 sm:px-16"
                                    >
                                        <MediaImage
                                            media={photo}
                                            conversion="full"
                                            sizes="100vw"
                                            loading={
                                                Math.abs(i - start) <= 1
                                                    ? 'eager'
                                                    : 'lazy'
                                            }
                                            alt={
                                                photo.alt ??
                                                `${name}, photo ${i + 1}`
                                            }
                                            className="max-h-full w-auto max-w-full object-contain"
                                        />
                                    </li>
                                ))}
                            </ul>
                        </div>
                        {photos.length > 1 && (
                            <>
                                <ArrowButton
                                    side="left"
                                    label="Previous photo"
                                    onClick={() => api?.scrollPrev()}
                                    large
                                />
                                <ArrowButton
                                    side="right"
                                    label="Next photo"
                                    onClick={() => api?.scrollNext()}
                                    large
                                />
                            </>
                        )}
                    </div>
                </DialogPrimitive.Content>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}
