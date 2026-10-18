import { Download, ExternalLink, FileText } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatBytes, isImage } from '@/lib/media';
import type { MediaItem } from '@/types';

/**
 * Admin viewer for private files (KYC documents, payment proofs). Nothing loads until
 * it is opened; each open requests the file again, which the server authorizes, logs
 * and answers with a short-lived URL.
 *
 * PDFs open in a new tab: the CSP's frame-src does not allow framing them (M12.3).
 */
export function SecureDocumentViewer({
    media,
    label,
}: {
    media: MediaItem;
    label?: string;
}) {
    const [openedAt, setOpenedAt] = useState<number | null>(null);
    const viewUrl = media.urls.view;
    const downloadUrl = media.urls.download;

    if (!viewUrl) {
        return null;
    }

    if (!isImage(media)) {
        return (
            <div className="flex flex-wrap items-center gap-2">
                <Button asChild variant="outline" size="sm">
                    <a href={viewUrl} target="_blank" rel="noopener noreferrer">
                        <FileText className="size-4" />
                        {label ?? media.name}
                        <ExternalLink className="size-3" />
                    </a>
                </Button>
                {downloadUrl && <DownloadButton href={downloadUrl} />}
            </div>
        );
    }

    return (
        <Dialog onOpenChange={(open) => setOpenedAt(open ? Date.now() : null)}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <FileText className="size-4" />
                    {label ?? media.name}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{label ?? media.name}</DialogTitle>
                    <DialogDescription>
                        {media.name} · {formatBytes(media.size)}. Viewing is
                        logged.
                    </DialogDescription>
                </DialogHeader>
                {openedAt !== null && (
                    <img
                        // A new request per open: never served from cache.
                        key={openedAt}
                        src={viewUrl}
                        alt={label ?? media.name}
                        className="max-h-[70vh] w-full rounded-md object-contain"
                    />
                )}
                {downloadUrl && (
                    <div className="flex justify-end">
                        <DownloadButton href={downloadUrl} />
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function DownloadButton({ href }: { href: string }) {
    return (
        <Button asChild variant="ghost" size="sm">
            <a href={href}>
                <Download className="size-4" />
                Download
            </a>
        </Button>
    );
}
