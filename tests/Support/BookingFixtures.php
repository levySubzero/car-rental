<?php

namespace Tests\Support;

use App\Enums\BookingDocument;
use App\Models\Booking;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Booking test setup: fake media disks with the real booking collections, and KYC files
 * attached without going through an upload.
 */
final class BookingFixtures
{
    public static function setUp(): void
    {
        Storage::fake('media-public-local', ['throw' => true]);
        Storage::fake('media-private-local', ['throw' => true]);
        config()->set('media.disks', ['public' => 'media-public-local', 'private' => 'media-private-local']);
    }

    /**
     * @param  list<BookingDocument>|null  $documents  defaults to what a Kenyan resident gives
     */
    public static function attachDocuments(Booking $booking, ?array $documents = null): void
    {
        $documents ??= [BookingDocument::IdFront, BookingDocument::IdBack, BookingDocument::KraCertificate, BookingDocument::Licence];

        foreach ($documents as $document) {
            Media::factory()->private()->attachedTo($booking, $document->collection())->create(['mime_type' => 'application/pdf']);
        }
    }
}
