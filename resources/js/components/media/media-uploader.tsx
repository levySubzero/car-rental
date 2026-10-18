import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { DragEndEvent } from '@dnd-kit/core';
import {
    arrayMove,
    rectSortingStrategy,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useHttp } from '@inertiajs/react';
import { FileText, GripVertical, Star, Upload, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Media/MediaController';
import { UploadTile } from '@/components/media/upload-tile';
import { Button } from '@/components/ui/button';
import {
    acceptAttribute,
    BOOKING_TOKEN_HEADER,
    coverOf,
    formatBytes,
    formatKilobytes,
    isImage,
    rejectionFor,
} from '@/lib/media';
import { cn } from '@/lib/utils';
import type { MediaCollectionLimits, MediaItem } from '@/types';

type Queued = { key: string; file: File };

/**
 * The one file input for every form (TECHNICAL_PLAN §6.6). Files upload as soon as
 * they are picked; the form only ever holds the resulting MediaItems and submits
 * their uuids, in order:
 *
 *     <MediaUploader limits={collections['car.gallery']} value={gallery}
 *         onChange={setGallery} sortable coverable cover={cover} onCoverChange={setCover} />
 *     form data: { gallery: gallery.map((m) => m.uuid), cover_media_id: cover }
 *
 * Removing a pending upload deletes it on the server. Removing a saved file only
 * drops it from the list; the owning service detaches it when the form is saved.
 */
export function MediaUploader({
    limits,
    value,
    onChange,
    sortable = false,
    coverable = false,
    cover,
    onCoverChange,
    bookingToken,
    disabled = false,
    error,
}: {
    limits: MediaCollectionLimits;
    value: MediaItem[];
    onChange: (items: MediaItem[]) => void;
    sortable?: boolean;
    coverable?: boolean;
    cover?: string | null;
    onCoverChange?: (uuid: string | null) => void;
    bookingToken?: string;
    disabled?: boolean;
    error?: string;
}) {
    const inputId = useId();
    const input = useRef<HTMLInputElement>(null);
    const [queue, setQueue] = useState<Queued[]>([]);
    const [rejected, setRejected] = useState<string[]>([]);
    const [dragging, setDragging] = useState(false);
    const http = useHttp();

    // Uploads can finish between renders; always append to the latest list.
    const latest = useRef(value);
    useEffect(() => {
        latest.current = value;
    }, [value]);

    const emit = (items: MediaItem[]) => {
        latest.current = items;
        onChange(items);
    };

    const remaining = limits.max_files - value.length - queue.length;
    const currentCover = coverOf(value, cover);
    const headers = bookingToken
        ? { [BOOKING_TOKEN_HEADER]: bookingToken }
        : {};

    const add = (files: FileList | File[]) => {
        const accepted: Queued[] = [];
        const reasons: string[] = [];

        for (const file of Array.from(files)) {
            const reason = rejectionFor(file, limits);

            if (reason) {
                reasons.push(`${file.name}: ${reason}`);
            } else if (accepted.length >= remaining) {
                reasons.push(
                    `${file.name}: only ${limits.max_files} file(s) allowed.`,
                );
            } else {
                accepted.push({ key: crypto.randomUUID(), file });
            }
        }

        setRejected(reasons);
        setQueue((current) => [...current, ...accepted]);
    };

    const uploaded = (key: string, item: MediaItem) => {
        setQueue((current) => current.filter((entry) => entry.key !== key));
        emit([...latest.current, item]);
    };

    const remove = (item: MediaItem) => {
        emit(latest.current.filter((entry) => entry.uuid !== item.uuid));

        if (item.uuid === currentCover) {
            onCoverChange?.(null);
        }

        if (item.status === 'pending') {
            void http
                .delete(MediaController.destroy.url(item.uuid), { headers })
                .catch(() => undefined);
        }
    };

    const reorder = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }

        const from = value.findIndex((item) => item.uuid === active.id);
        const to = value.findIndex((item) => item.uuid === over.id);

        emit(arrayMove(value, from, to));
    };

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const canAdd = !disabled && remaining > 0;

    return (
        <div className="grid gap-3">
            <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={reorder}
            >
                <SortableContext
                    items={value.map((item) => item.uuid)}
                    strategy={rectSortingStrategy}
                    disabled={!sortable || disabled}
                >
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {value.map((item) => (
                            <SortableTile
                                key={item.uuid}
                                id={item.uuid}
                                sortable={sortable && !disabled}
                            >
                                <SavedTile
                                    item={item}
                                    isCover={
                                        coverable && item.uuid === currentCover
                                    }
                                    onCover={
                                        coverable && !disabled
                                            ? () => onCoverChange?.(item.uuid)
                                            : undefined
                                    }
                                    onRemove={
                                        disabled
                                            ? undefined
                                            : () => remove(item)
                                    }
                                />
                            </SortableTile>
                        ))}
                        {queue.map((entry) => (
                            <li key={entry.key}>
                                <UploadTile
                                    file={entry.file}
                                    limits={limits}
                                    bookingToken={bookingToken}
                                    onUploaded={(item) =>
                                        uploaded(entry.key, item)
                                    }
                                    onDiscard={() =>
                                        setQueue((current) =>
                                            current.filter(
                                                (queued) =>
                                                    queued.key !== entry.key,
                                            ),
                                        )
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                </SortableContext>
            </DndContext>

            {canAdd && (
                <label
                    htmlFor={inputId}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={(event) => {
                        event.preventDefault();
                        setDragging(false);
                        add(event.dataTransfer.files);
                    }}
                    className={cn(
                        'flex cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground transition-colors focus-within:ring-2 focus-within:ring-ring hover:bg-accent/50',
                        dragging && 'border-primary bg-accent/50',
                        error && 'border-destructive',
                    )}
                >
                    <Upload className="size-5" />
                    <span className="font-medium text-foreground">
                        {value.length + queue.length === 0
                            ? 'Add files'
                            : 'Add more'}
                    </span>
                    <span className="text-xs">
                        {limits.mimes
                            .map((ext) => ext.toUpperCase())
                            .join(', ')}{' '}
                        · up to {formatKilobytes(limits.max_kb)} · {remaining}{' '}
                        of {limits.max_files} left
                    </span>
                    <input
                        ref={input}
                        id={inputId}
                        type="file"
                        className="sr-only"
                        accept={acceptAttribute(limits)}
                        multiple={remaining > 1}
                        onChange={(event) => {
                            if (event.target.files) {
                                add(event.target.files);
                            }

                            // Let the same file be picked again after removal.
                            event.target.value = '';
                        }}
                    />
                </label>
            )}

            {[...rejected, ...(error ? [error] : [])].map((message) => (
                <p
                    key={message}
                    className="text-sm text-destructive"
                    role="alert"
                >
                    {message}
                </p>
            ))}
        </div>
    );
}

function SavedTile({
    item,
    isCover,
    onCover,
    onRemove,
}: {
    item: MediaItem;
    isCover: boolean;
    onCover?: () => void;
    onRemove?: () => void;
}) {
    const thumbnail = item.urls.thumb ?? item.urls.original;

    return (
        <div
            className={cn(
                'relative aspect-[4/3] overflow-hidden rounded-md border bg-muted',
                isCover && 'ring-2 ring-primary',
            )}
        >
            {thumbnail && isImage(item) ? (
                <img
                    src={thumbnail}
                    alt={item.alt ?? ''}
                    loading="lazy"
                    className="h-full w-full object-cover"
                />
            ) : (
                <div className="flex h-full flex-col items-center justify-center gap-1 p-2 text-center text-xs text-muted-foreground">
                    <FileText className="size-8" />
                    <span className="w-full truncate" title={item.name}>
                        {item.name}
                    </span>
                    <span>{formatBytes(item.size)}</span>
                </div>
            )}

            <div className="absolute top-1 right-1 flex gap-1">
                {onCover && (
                    <Button
                        type="button"
                        size="icon"
                        variant={isCover ? 'default' : 'secondary'}
                        className="size-7"
                        onClick={onCover}
                        aria-pressed={isCover}
                        aria-label={isCover ? 'Cover image' : 'Use as cover'}
                    >
                        <Star
                            className={cn('size-4', isCover && 'fill-current')}
                        />
                    </Button>
                )}
                {onRemove && (
                    <Button
                        type="button"
                        size="icon"
                        variant="secondary"
                        className="size-7"
                        onClick={onRemove}
                        aria-label={`Remove ${item.name}`}
                    >
                        <X className="size-4" />
                    </Button>
                )}
            </div>

            {isCover && (
                <span className="absolute bottom-1 left-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground">
                    Cover
                </span>
            )}
        </div>
    );
}

function SortableTile({
    id,
    sortable,
    children,
}: {
    id: string;
    sortable: boolean;
    children: ReactNode;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id, disabled: !sortable });

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn('relative', isDragging && 'z-10 shadow-lg')}
        >
            {children}
            {sortable && (
                <button
                    type="button"
                    className="absolute top-1 left-1 cursor-grab touch-none rounded bg-secondary p-1 text-muted-foreground hover:bg-accent active:cursor-grabbing"
                    aria-label="Drag to reorder"
                    {...attributes}
                    {...listeners}
                >
                    <GripVertical className="size-4" />
                </button>
            )}
        </li>
    );
}
