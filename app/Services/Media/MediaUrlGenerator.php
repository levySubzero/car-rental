<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * The only place media URLs are built (TECHNICAL_PLAN §6.5), so switching a disk
 * to S3 or a CDN changes nothing else.
 */
class MediaUrlGenerator
{
    /**
     * A stable URL for the file. Public files: the disk or CDN URL when
     * media.serve_public_via is "redirect". Private files: the app route, which
     * authorizes and logs each request.
     */
    public function url(Media $media, ?string $conversion = null): string
    {
        if ($media->isPublic() && config('media.serve_public_via') === 'redirect') {
            return Storage::disk($media->disk)->url($this->pathFor($media, $conversion));
        }

        return route('media.show', array_filter(['media' => $media->uuid, 'conversion' => $this->conversionFor($media, $conversion)]));
    }

    /**
     * A short-lived URL for a file the caller is already authorized to see: an S3
     * presigned URL, or a signed app route on local disks.
     */
    public function temporaryUrl(Media $media, ?string $conversion = null, bool $download = false): string
    {
        $expires = now()->addMinutes((int) config('media.signed_url_ttl'));

        if ($this->isS3($media->disk)) {
            return Storage::disk($media->disk)->temporaryUrl(
                $this->pathFor($media, $conversion),
                $expires,
                $download ? ['ResponseContentDisposition' => 'attachment; filename="'.$this->downloadName($media).'"'] : [],
            );
        }

        return URL::temporarySignedRoute('media.signed', $expires, array_filter([
            'media' => $media->uuid,
            'conversion' => $this->conversionFor($media, $conversion),
            'download' => $download ? 1 : null,
        ]));
    }

    /**
     * Every URL a client needs, for MediaResource. Private files only get app routes.
     *
     * @return array<string, string>
     */
    public function urlsFor(Media $media): array
    {
        if (! $media->isPublic()) {
            return [
                'view' => route('media.show', $media->uuid),
                'download' => route('media.download', $media->uuid),
            ];
        }

        $urls = ['original' => $this->url($media)];

        foreach (array_keys((array) config('media.conversions')) as $name) {
            $urls[$name] = $this->url($media, $name);
        }

        return $urls;
    }

    /**
     * The stored path of a conversion, or of the original while it is not ready.
     */
    public function pathFor(Media $media, ?string $conversion = null): string
    {
        return $conversion !== null && isset($media->conversions[$conversion])
            ? $media->conversions[$conversion]
            : $media->path;
    }

    public function downloadName(Media $media): string
    {
        $base = pathinfo($media->original_name, PATHINFO_FILENAME);
        $safe = trim((string) preg_replace('/[^A-Za-z0-9 ._-]+/', '', $base)) ?: 'file';

        return "{$safe}.{$media->extension}";
    }

    public function isS3(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }

    private function conversionFor(Media $media, ?string $conversion): ?string
    {
        return $conversion !== null && isset($media->conversions[$conversion]) ? $conversion : null;
    }
}
