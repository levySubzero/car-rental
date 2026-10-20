<?php

/*
|--------------------------------------------------------------------------
| Media module (TECHNICAL_PLAN §6)
|--------------------------------------------------------------------------
|
| Every file in the app goes through the Media module. Each media row
| records the disk it was written to, so changing a disk below only
| affects new uploads; `media:migrate-disk` moves existing files.
|
*/

return [

    'disks' => [
        'public' => env('MEDIA_PUBLIC_DISK', 'media-public-local'),   // or media-public-s3
        'private' => env('MEDIA_PRIVATE_DISK', 'media-private-local'), // or media-private-s3
    ],

    // Minutes a private file's temporary URL stays valid.
    'signed_url_ttl' => (int) env('MEDIA_SIGNED_URL_TTL', 5),

    // Minutes an unattached upload is kept before `media:cleanup` removes it.
    'pending_ttl' => (int) env('MEDIA_PENDING_TTL', 1440),

    // Days a deleted file is kept before its storage is purged.
    'purge_after_days' => (int) env('MEDIA_PURGE_AFTER_DAYS', 7),

    // Public files: `redirect` to the disk or CDN URL, or `stream` through the app.
    'serve_public_via' => env('MEDIA_PUBLIC_SERVE', 'redirect'),

    // `imagick` or `gd`. Null picks Imagick when the extension is loaded.
    // HEIC uploads are only accepted when Imagick can decode them.
    'image_driver' => env('MEDIA_IMAGE_DRIVER'),

    'conversions' => [
        'thumb' => ['width' => 320, 'format' => 'webp', 'quality' => 75],
        'card' => ['width' => 800, 'format' => 'webp', 'quality' => 80],
        'full' => ['width' => 1920, 'format' => 'webp', 'quality' => 82],
    ],

    // Sniffed MIME type => stored extension. Collections list extensions from here.
    'mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heic',
        'application/pdf' => 'pdf',
    ],

    /*
    | Collections are registered by the module that owns them (D16). Keys:
    |
    | owner        morph alias of the owning model, or null for a standalone file
    |              (attached with MediaService::claim, e.g. a settings value)
    | visibility   public | private. Private files always use the private disk.
    | max_files    hard ceiling; admins may lower it (MediaSettings)
    | max_kb       hard ceiling, never above baines.uploads.max_kb_ceiling
    | mimes        extensions from mime_types; admins may narrow the list
    | conversions  names from `conversions`, generated for images
    | upload       who may upload: ['permission' => 'x.update'], ['token' => true]
    |              for renters with a booking token, or ['system' => true]
    */
    'collections' => [

        'site.hero' => [
            'label' => 'Home page hero',
            'owner' => null,
            'visibility' => 'public',
            'max_files' => 1,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic'],
            'conversions' => ['thumb', 'card', 'full'],
            'upload' => ['permission' => 'settings.home.update'],
        ],

        // Catalog (M4). SVG logos would need sanitising, so logos are raster for now (D21).
        'brand.logo' => [
            'label' => 'Brand logo',
            'owner' => 'brand',
            'visibility' => 'public',
            'max_files' => 1,
            'max_kb' => 2048,
            'mimes' => ['png', 'webp'],
            'conversions' => ['thumb', 'card'],
            'upload' => ['permission' => 'brands.update'],
        ],

        'car.gallery' => [
            'label' => 'Car photos',
            'owner' => 'car',
            'visibility' => 'public',
            'max_files' => 6,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic'],
            'conversions' => ['thumb', 'card', 'full'],
            'upload' => ['permission' => 'cars.update'],
        ],

    ],

];
