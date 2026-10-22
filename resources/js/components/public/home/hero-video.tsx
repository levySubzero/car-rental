import { useEffect, useRef } from 'react';

/**
 * The optional hero loop, over the hero image (its poster and fallback). It only plays
 * once the page is running in the browser and the visitor allows motion; with reduced
 * motion it stays hidden and the image shows.
 */
export function HeroVideo({
    src,
    type,
    poster,
}: {
    src: string;
    type: string;
    poster?: string;
}) {
    const video = useRef<HTMLVideoElement>(null);

    useEffect(() => {
        const element = video.current;

        if (
            !element ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        // Browsers allow muted autoplay; a refusal just leaves the poster showing.
        element.play().catch(() => undefined);
    }, []);

    return (
        <video
            ref={video}
            muted
            loop
            playsInline
            preload="metadata"
            poster={poster}
            aria-hidden
            className="absolute inset-0 size-full object-cover motion-reduce:hidden"
        >
            <source src={src} type={type} />
        </video>
    );
}
