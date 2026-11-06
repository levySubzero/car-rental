import { Check, Link2, Share2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BrandIcon } from '@/components/public/icons';
import { Button } from '@/components/ui/button';

/**
 * Sends a car's link somewhere else: WhatsApp, the phone's share sheet, or the clipboard.
 * Instagram has no web share link, so its stories and DMs go through the other two.
 */
export function ShareActions({
    url,
    text,
    compact = false,
}: {
    url: string;
    text: string;
    compact?: boolean;
}) {
    const [canShare, setCanShare] = useState(false);
    const [copied, setCopied] = useState(false);

    // Decided after mounting, because the server render has no navigator.
    useEffect(() => setCanShare(typeof navigator.share === 'function'), []);

    const copy = async () => {
        await navigator.clipboard.writeText(url);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    const share = () => {
        // Closing the share sheet rejects the promise, which is not an error.
        navigator.share({ title: text, url }).catch(() => {});
    };

    const size = compact ? 'icon' : 'default';

    return (
        <div className="flex flex-wrap items-center gap-2">
            {!compact && (
                <span className="text-sm text-muted-foreground">Share</span>
            )}
            <Button asChild variant="outline" size={size}>
                <a
                    href={`https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={`Share ${text} on WhatsApp`}
                >
                    <BrandIcon name="whatsapp" className="size-4" />
                    {!compact && 'WhatsApp'}
                </a>
            </Button>
            {canShare && (
                <Button
                    type="button"
                    variant="outline"
                    size={size}
                    onClick={share}
                    aria-label={`Share ${text}`}
                >
                    <Share2 className="size-4" />
                    {!compact && 'More'}
                </Button>
            )}
            <Button
                type="button"
                variant="outline"
                size={size}
                onClick={copy}
                aria-label={copied ? 'Link copied' : `Copy link to ${text}`}
            >
                {copied ? (
                    <Check className="size-4" />
                ) : (
                    <Link2 className="size-4" />
                )}
                {!compact && (copied ? 'Copied' : 'Copy link')}
            </Button>
        </div>
    );
}
