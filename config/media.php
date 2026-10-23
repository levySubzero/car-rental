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
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
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
    | guidance     optional advice the uploader shows: shape, recommended width and
    |              height in pixels, and a framing tip. A page image field can give
    |              its own in config/pages.php.
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
            'guidance' => [
                'shape' => 'Wide 16:9',
                'width' => 2400,
                'height' => 1350,
                'tip' => 'Keep the left half fairly plain and dark: the headline sits there. This image also shows while a video loads, on slow connections and for visitors who turn motion off.',
            ],
        ],

        // An optional short loop over the hero image. Videos are stored as uploaded (no conversions).
        'site.hero_video' => [
            'label' => 'Home page hero video',
            'owner' => null,
            'visibility' => 'public',
            'max_files' => 1,
            'max_kb' => 20480,
            'mimes' => ['mp4', 'webm'],
            'conversions' => [],
            'upload' => ['permission' => 'settings.home.update'],
            'guidance' => [
                'shape' => 'Wide 16:9',
                'width' => 1920,
                'height' => 1080,
                'tip' => 'MP4 (H.264) or WebM, 10 to 20 seconds, slow and steady. It plays muted on a loop, so sound is not needed. Under 10 MB loads quickly on phones.',
            ],
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
            'guidance' => [
                'shape' => 'Wide, transparent background',
                'width' => 600,
                'height' => 300,
                'tip' => 'The official logo, cropped close, as a PNG or WebP with a transparent background. It shows in grey and turns to colour on hover.',
            ],
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
            'guidance' => [
                'shape' => 'Landscape 4:3',
                'width' => 1600,
                'height' => 1200,
                'tip' => 'The first photo is the cover: a front three-quarter view. Then side, rear, dashboard, rear seats and boot. Use the same place and time of day for every car.',
            ],
        ],

        // Content (M5)
        'service.image' => [
            'label' => 'Service image',
            'owner' => 'service',
            'visibility' => 'public',
            'max_files' => 1,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic'],
            'conversions' => ['thumb', 'card', 'full'],
            'upload' => ['permission' => 'services.update'],
            'guidance' => [
                'shape' => 'Upright 4:5',
                'width' => 1200,
                'height' => 1500,
                'tip' => 'Shown on the home page service cards and the Services page. Keep the subject in the upper half: the lower part of the card is darkened for the text.',
            ],
        ],

        // Every image field of a preset page (PageRegistry); the page keeps the uuids in its content.
        'page.image' => [
            'label' => 'Page image',
            'owner' => 'page',
            'visibility' => 'public',
            'max_files' => 6,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic'],
            'conversions' => ['thumb', 'card', 'full'],
            'upload' => ['permission' => 'pages.update'],
            'guidance' => [
                'shape' => 'Landscape 4:3',
                'width' => 1600,
                'height' => 1200,
            ],
        ],

        // Booking KYC (M8.12): private, uploaded by the renter with the booking token. Which
        // ones a driver must give is BookingSettings::document_requirements (BookingDocument).
        'booking.id_front' => [
            'label' => 'ID card, front',
            'owner' => 'booking',
            'visibility' => 'private',
            'max_files' => 1,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic', 'pdf'],
            'conversions' => [],
            'upload' => ['token' => true],
        ],

        'booking.id_back' => [
            'label' => 'ID card, back',
            'owner' => 'booking',
            'visibility' => 'private',
            'max_files' => 1,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic', 'pdf'],
            'conversions' => [],
            'upload' => ['token' => true],
        ],

        'booking.passport' => [
            'label' => 'Passport photo page',
            'owner' => 'booking',
            'visibility' => 'private',
            'max_files' => 1,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic', 'pdf'],
            'conversions' => [],
            'upload' => ['token' => true],
        ],

        'booking.kra_certificate' => [
            'label' => 'KRA PIN certificate',
            'owner' => 'booking',
            'visibility' => 'private',
            'max_files' => 1,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic', 'pdf'],
            'conversions' => [],
            'upload' => ['token' => true],
        ],

        'booking.licence' => [
            'label' => 'Driving licence',
            'owner' => 'booking',
            'visibility' => 'private',
            'max_files' => 1,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic', 'pdf'],
            'conversions' => [],
            'upload' => ['token' => true],
        ],

        // Payment proof (M9.3): a screenshot or receipt, one per payment attempt. Uploaded with
        // the booking token while the booking waits for payment.
        'booking.payment_proof' => [
            'label' => 'Payment proof',
            'owner' => 'booking',
            'visibility' => 'private',
            'max_files' => 5,
            'max_kb' => 8192,
            'mimes' => ['jpg', 'png', 'webp', 'heic', 'pdf'],
            'conversions' => [],
            'upload' => ['token' => true],
        ],

    ],

];
