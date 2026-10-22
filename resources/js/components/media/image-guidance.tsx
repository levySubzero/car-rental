import { AlertTriangle } from 'lucide-react';
import type { MediaGuidance, MediaItem } from '@/types';

/**
 * What to upload: the shape (drawn to scale), the recommended size and a framing tip,
 * from the collection's or the page field's guidance (config/media.php, config/pages.php).
 */
export function ImageGuidance({ guidance }: { guidance: MediaGuidance }) {
    const ratio = guidance.width / guidance.height;
    // The frame fits a 64 × 48 box, so tall and wide shapes compare at a glance.
    const width = ratio >= 4 / 3 ? 64 : Math.round(48 * ratio);
    const height = ratio >= 4 / 3 ? Math.round(64 / ratio) : 48;

    return (
        <div className="flex gap-3 rounded-md border bg-muted/40 p-3 text-xs text-muted-foreground">
            <div
                aria-hidden
                className="flex size-16 shrink-0 items-center justify-center"
            >
                <div
                    className="rounded-sm border-2 border-dashed border-muted-foreground/60"
                    style={{ width, height }}
                />
            </div>
            <div className="grid min-w-0 gap-1">
                <p className="font-medium text-foreground">
                    {guidance.shape}, at least{' '}
                    {guidance.width.toLocaleString('en')} ×{' '}
                    {guidance.height.toLocaleString('en')} px
                </p>
                {guidance.tip && <p>{guidance.tip}</p>}
            </div>
        </div>
    );
}

/**
 * Images clearly smaller than recommended, which will look soft once enlarged.
 */
export function SmallImageWarning({
    items,
    guidance,
}: {
    items: MediaItem[];
    guidance: MediaGuidance;
}) {
    const small = items.filter(
        (item) =>
            item.mime.startsWith('image/') &&
            item.width !== null &&
            item.height !== null &&
            (item.width < guidance.width * 0.75 ||
                item.height < guidance.height * 0.75),
    );

    if (small.length === 0) {
        return null;
    }

    return (
        <ul className="grid gap-1 text-xs text-amber-700 dark:text-amber-400">
            {small.map((item) => (
                <li key={item.uuid} className="flex items-start gap-1.5">
                    <AlertTriangle
                        aria-hidden
                        className="mt-0.5 size-3.5 shrink-0"
                    />
                    <span>
                        {item.name} is {item.width} × {item.height} px, smaller
                        than the recommended {guidance.width} ×{' '}
                        {guidance.height} px, so it may look soft on large
                        screens.
                    </span>
                </li>
            ))}
        </ul>
    );
}
