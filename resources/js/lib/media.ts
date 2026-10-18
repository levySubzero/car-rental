import imageCompression from 'browser-image-compression';
import type { MediaCollectionLimits, MediaItem } from '@/types';

/**
 * Media helpers shared by the uploader and the admin (TECHNICAL_PLAN §6.6).
 * The server re-checks everything here; these checks only save a wasted upload.
 */

export const BOOKING_TOKEN_HEADER = 'X-Booking-Token';

// Phone photos above this are shrunk before upload. HEIC is left to the server.
const COMPRESS_ABOVE_BYTES = 1.5 * 1024 * 1024;
const COMPRESSIBLE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_DIMENSION = 2560;

export function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    const mb = bytes / (1024 * 1024);

    return `${Number.isInteger(mb) ? mb : mb.toFixed(1)} MB`;
}

export function formatKilobytes(kb: number): string {
    return formatBytes(kb * 1024);
}

/**
 * The <input accept> value: MIME types plus extensions, because some browsers do not
 * know image/heic.
 */
export function acceptAttribute(limits: MediaCollectionLimits): string {
    return [...limits.accept, ...limits.mimes.map((ext) => `.${ext}`)].join(
        ',',
    );
}

function extensionOf(name: string): string {
    const ext = name.split('.').pop()?.toLowerCase() ?? '';

    return ext === 'jpeg' ? 'jpg' : ext;
}

/**
 * A reason to refuse the file before uploading, or null. Checked by name and size only;
 * the server sniffs the real type.
 */
export function rejectionFor(
    file: File,
    limits: MediaCollectionLimits,
): string | null {
    if (!limits.mimes.includes(extensionOf(file.name))) {
        return `Use ${limits.mimes.map((ext) => ext.toUpperCase()).join(', ')}.`;
    }

    if (file.size > limits.max_kb * 1024) {
        return `Larger than ${formatKilobytes(limits.max_kb)}.`;
    }

    return null;
}

/**
 * Shrink large JPEG, PNG and WebP photos in the browser (mobile data, upload limits).
 * Anything else, or any failure, returns the original file.
 */
export async function compressIfNeeded(
    file: File,
    maxKb: number,
): Promise<File> {
    if (
        !COMPRESSIBLE_TYPES.includes(file.type) ||
        file.size <= COMPRESS_ABOVE_BYTES
    ) {
        return file;
    }

    try {
        const compressed = await imageCompression(file, {
            maxSizeMB: Math.min(2, maxKb / 1024),
            maxWidthOrHeight: MAX_DIMENSION,
            initialQuality: 0.85,
            useWebWorker: true,
        });

        return compressed.size < file.size
            ? new File([compressed], file.name, {
                  type: compressed.type,
                  lastModified: file.lastModified,
              })
            : file;
    } catch {
        return file;
    }
}

export function isImage(media: Pick<MediaItem, 'mime'>): boolean {
    return media.mime.startsWith('image/');
}

/**
 * The cover: the chosen uuid when it is still in the list, otherwise the first file.
 */
export function coverOf(
    items: MediaItem[],
    cover?: string | null,
): string | null {
    return items.some((item) => item.uuid === cover)
        ? (cover ?? null)
        : (items[0]?.uuid ?? null);
}
