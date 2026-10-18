/**
 * Mirrors MediaCollection::toArray() (app/Support/Media/MediaCollection.php).
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
