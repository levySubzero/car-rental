<?php

namespace Database\Factories;

use App\Enums\MediaProcessing;
use App\Enums\MediaStatus;
use App\Enums\MediaVisibility;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Rows only: no file is written. Tests that need bytes on disk upload through MediaService.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();
        $fileName = Str::random(40).'.jpg';

        return [
            'uuid' => $uuid,
            'collection' => 'site.hero',
            'disk' => MediaVisibility::Public->disk(),
            'path' => 'site.hero/'.now()->format('Y/m')."/{$uuid}/{$fileName}",
            'conversions' => null,
            'original_name' => fake()->word().'.jpg',
            'file_name' => $fileName,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => fake()->numberBetween(10_000, 2_000_000),
            'checksum_sha256' => hash('sha256', $uuid),
            'width' => 1600,
            'height' => 1067,
            'visibility' => MediaVisibility::Public,
            'status' => MediaStatus::Pending,
            'processing' => MediaProcessing::Skipped,
            'is_primary' => false,
            'sort_order' => 0,
            'metadata' => [],
            'expires_at' => now()->addMinutes((int) config('media.pending_ttl')),
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => [
            'visibility' => MediaVisibility::Private,
            'disk' => MediaVisibility::Private->disk(),
        ]);
    }

    public function attachedTo(?Model $owner, ?string $collection = null): static
    {
        return $this->state(fn (array $attributes) => [
            'collection' => $collection ?? $attributes['collection'],
            'mediable_type' => $owner?->getMorphClass(),
            'mediable_id' => $owner?->getKey(),
            'status' => MediaStatus::Attached,
            'expires_at' => null,
        ]);
    }

    public function uploadedBy(Model $uploader): static
    {
        return $this->state(fn () => [
            'uploaded_by_type' => $uploader->getMorphClass(),
            'uploaded_by_id' => $uploader->getKey(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => MediaStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
