<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\ReorderMediaRequest;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Http\Resources\Media\MediaResource;
use App\Models\Media;
use App\Services\Media\CurrentUploader;
use App\Services\Media\MediaService;
use App\Services\Media\MediaUrlGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only media endpoints in the app (TECHNICAL_PLAN §6.4). Authorization runs as
 * CurrentUploader (qualified staff or a booking token), see ResolveMediaUploader.
 */
class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly MediaUrlGenerator $urls,
        private readonly CurrentUploader $uploader,
    ) {}

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $uploader = $this->uploader->actor() ?? abort(403);
        $media = $this->media->upload($request->uploadedFile(), (string) $request->validated('collection'), $uploader);

        return MediaResource::make($media)->response()->setStatusCode(201);
    }

    /**
     * Public: redirect to the disk or CDN, cached for a year (paths never change).
     * Private: authorize, log, then redirect to a short-lived URL.
     */
    public function show(Request $request, Media $media, ?string $conversion = null): RedirectResponse|StreamedResponse
    {
        $this->authorizeAs('view', $media);

        if ($media->isPublic()) {
            if (config('media.serve_public_via') !== 'redirect') {
                return $this->stream($media, $conversion, cache: true);
            }

            return redirect()->away($this->urls->url($media, $conversion))
                ->header('Cache-Control', 'public, max-age=31536000, immutable');
        }

        $this->media->logAccess($media, 'viewed', $this->uploader->actor(), $request->ip(), $conversion);

        return redirect()->away($this->urls->temporaryUrl($media, $conversion))
            ->header('Cache-Control', 'no-store, private');
    }

    public function download(Request $request, Media $media): RedirectResponse|StreamedResponse
    {
        $this->authorizeAs('download', $media);

        if (! $media->isPublic()) {
            $this->media->logAccess($media, 'downloaded', $this->uploader->actor(), $request->ip());
        }

        if ($this->urls->isS3($media->disk)) {
            return redirect()->away($this->urls->temporaryUrl($media, download: true))
                ->header('Cache-Control', 'no-store, private');
        }

        return $this->stream($media, null, download: true);
    }

    /**
     * Target of temporary signed URLs on local disks. The signature is the authorization;
     * the request that produced it was authorized and logged.
     */
    public function signed(Request $request, Media $media, ?string $conversion = null): StreamedResponse
    {
        return $this->stream($media, $conversion, download: $request->boolean('download'));
    }

    public function update(UpdateMediaRequest $request, Media $media): MediaResource
    {
        /** @var array{alt?: string|null, caption?: string|null} $data */
        $data = $request->validated();

        return MediaResource::make($this->media->updateMetadata($media, $data, $this->uploader->actor()));
    }

    public function destroy(Media $media): Response
    {
        $this->authorizeAs('delete', $media);

        $this->media->delete($media, $this->uploader->actor());

        return response()->noContent();
    }

    public function reorder(ReorderMediaRequest $request): Response
    {
        $this->media->reorder($request->owner(), (string) $request->validated('collection'), $request->uuids(), $this->uploader->actor());

        return response()->noContent();
    }

    private function authorizeAs(string $ability, Media $media): void
    {
        abort_unless($this->uploader->allows($ability, $media), 403);
    }

    private function stream(Media $media, ?string $conversion, bool $download = false, bool $cache = false): StreamedResponse
    {
        $file = $this->media->open($media, $conversion);
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$file['name'].'"';

        return response()->stream(function () use ($file): void {
            fpassthru($file['stream']);
            fclose($file['stream']);
        }, 200, [
            'Content-Type' => $file['mime'],
            'Content-Length' => (string) $file['size'],
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $cache ? 'public, max-age=31536000, immutable' : 'no-store, private',
        ]);
    }
}
