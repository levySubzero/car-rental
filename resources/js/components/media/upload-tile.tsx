import { useHttp } from '@inertiajs/react';
import { FileText, RotateCw, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Media/MediaController';
import { Button } from '@/components/ui/button';
import { BOOKING_TOKEN_HEADER, compressIfNeeded } from '@/lib/media';
import { cn } from '@/lib/utils';
import type { MediaCollectionLimits, MediaItem } from '@/types';

/**
 * One file being uploaded. Starts on mount; reports the MediaResource when done.
 * Each tile has its own useHttp, so files upload in parallel with their own progress.
 */
export function UploadTile({
    file,
    limits,
    bookingToken,
    onUploaded,
    onDiscard,
}: {
    file: File;
    limits: MediaCollectionLimits;
    bookingToken?: string;
    onUploaded: (item: MediaItem) => void;
    onDiscard: () => void;
}) {
    const http = useHttp<{ collection: string; file: File }, MediaItem>({
        collection: limits.key,
        file,
    });
    const prepared = useRef<File | null>(null);
    const started = useRef(false);
    const [failed, setFailed] = useState<string | null>(null);
    const preview = useObjectUrl(file);

    const upload = async () => {
        setFailed(null);

        try {
            prepared.current ??= await compressIfNeeded(file, limits.max_kb);
            http.transform((data) => ({
                ...data,
                file: prepared.current ?? data.file,
            }));

            const item = await http.post(MediaController.store.url(), {
                headers: bookingToken
                    ? { [BOOKING_TOKEN_HEADER]: bookingToken }
                    : {},
            });

            // A 422 resolves to undefined; the message is in http.errors.
            if (item) {
                onUploaded(item);
            }
        } catch {
            setFailed('Upload failed. Check your connection and try again.');
        }
    };

    useEffect(() => {
        // Once per tile, also under StrictMode's double effects.
        if (!started.current) {
            started.current = true;
            void upload();
        }
    }, []);

    const error = http.errors.file ?? failed;
    const percent = http.progress?.percentage ?? 0;

    return (
        <div
            className={cn(
                'relative flex aspect-[4/3] flex-col overflow-hidden rounded-md border bg-muted',
                error && 'border-destructive',
            )}
        >
            {preview ? (
                <img
                    src={preview}
                    alt=""
                    className={cn(
                        'h-full w-full object-cover',
                        !error && 'opacity-60',
                    )}
                />
            ) : (
                <div className="flex flex-1 items-center justify-center text-muted-foreground">
                    <FileText className="size-8" />
                </div>
            )}

            <div className="absolute inset-x-0 bottom-0 space-y-1 bg-background/90 p-2 text-xs">
                <p className="truncate font-medium" title={file.name}>
                    {file.name}
                </p>
                {error ? (
                    <div className="flex items-start justify-between gap-2">
                        <p className="text-destructive" role="alert">
                            {error}
                        </p>
                        {failed && (
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                className="size-6 shrink-0"
                                onClick={() => void upload()}
                                aria-label={`Retry ${file.name}`}
                            >
                                <RotateCw className="size-3" />
                            </Button>
                        )}
                    </div>
                ) : (
                    <div
                        className="h-1 overflow-hidden rounded bg-muted"
                        role="progressbar"
                        aria-label={`Uploading ${file.name}`}
                        aria-valuenow={percent}
                        aria-valuemin={0}
                        aria-valuemax={100}
                    >
                        <div
                            className="h-full bg-primary transition-[width]"
                            style={{ width: `${percent}%` }}
                        />
                    </div>
                )}
            </div>

            <Button
                type="button"
                size="icon"
                variant="secondary"
                className="absolute top-1 right-1 size-7"
                onClick={() => {
                    http.cancel();
                    onDiscard();
                }}
                aria-label={`Cancel ${file.name}`}
            >
                <X className="size-4" />
            </Button>
        </div>
    );
}

function useObjectUrl(file: File): string | null {
    const url = useMemo(
        () =>
            file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        [file],
    );

    useEffect(
        () => () => {
            if (url) {
                URL.revokeObjectURL(url);
            }
        },
        [url],
    );

    return url;
}
