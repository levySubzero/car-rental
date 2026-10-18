/**
 * Mirrors MediaCollection::toArray() (app/Support/Media/MediaCollection.php).
 * Pages that render an uploader receive these limits as a prop.
 */
export type MediaCollectionLimits = {
    key: string;
    label: string;
    visibility: 'public' | 'private';
    max_files: number;
    max_kb: number;
    mimes: string[];
    accept: string[];
};

export type MediaConversion = 'thumb' | 'card' | 'full';

/**
 * Mirrors App\Http\Resources\Media\MediaResource. Public files carry direct URLs per
 * conversion; private files only carry app routes that authorize and log each request.
 */
export type MediaItem = {
    uuid: string;
    collection: string;
    name: string;
    mime: string;
    size: number;
    width: number | null;
    height: number | null;
    alt: string | null;
    caption: string | null;
    visibility: 'public' | 'private';
    status: 'pending' | 'attached';
    processing: 'queued' | 'done' | 'failed' | 'skipped';
    is_primary: boolean;
    sort_order: number;
    urls: Partial<Record<'original' | MediaConversion, string>> & {
        view?: string;
        download?: string;
    };
};
