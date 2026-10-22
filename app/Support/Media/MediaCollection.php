<?php

namespace App\Support\Media;

use App\Enums\MediaVisibility;
use LogicException;

/**
 * One media collection definition (config/media.php), after admin overrides are applied.
 */
final readonly class MediaCollection
{
    /**
     * @param  list<string>  $mimes  allowed extensions (values of media.mime_types)
     * @param  list<string>  $conversions
     * @param  array{shape: string, width: int, height: int, tip?: string}|null  $guidance  shown by the uploader
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $owner,
        public MediaVisibility $visibility,
        public int $maxFiles,
        public int $maxKb,
        public array $mimes,
        public array $conversions,
        public ?string $uploadPermission = null,
        public bool $tokenUpload = false,
        public bool $systemUpload = false,
        public ?array $guidance = null,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromConfig(string $key, array $definition, int $kbCeiling): self
    {
        $upload = (array) ($definition['upload'] ?? []);

        $collection = new self(
            key: $key,
            label: (string) ($definition['label'] ?? $key),
            owner: isset($definition['owner']) ? (string) $definition['owner'] : null,
            visibility: MediaVisibility::from((string) ($definition['visibility'] ?? 'public')),
            maxFiles: max(1, (int) ($definition['max_files'] ?? 1)),
            maxKb: min($kbCeiling, max(1, (int) ($definition['max_kb'] ?? $kbCeiling))),
            mimes: array_values(array_map('strval', (array) ($definition['mimes'] ?? []))),
            conversions: array_values(array_map('strval', (array) ($definition['conversions'] ?? []))),
            uploadPermission: isset($upload['permission']) ? (string) $upload['permission'] : null,
            tokenUpload: (bool) ($upload['token'] ?? false),
            systemUpload: (bool) ($upload['system'] ?? false),
            guidance: self::guidanceFrom($definition['guidance'] ?? null),
        );

        $collection->assertValid();

        return $collection;
    }

    /**
     * Apply admin overrides. They can only tighten: values above a ceiling and
     * extensions outside the configured list are ignored.
     *
     * @param  array{max_files?: int|null, max_kb?: int|null, mimes?: list<string>|null}  $overrides
     */
    public function tightenedBy(array $overrides): self
    {
        $mimes = $this->mimes;

        if (! empty($overrides['mimes'])) {
            $narrowed = array_values(array_intersect($this->mimes, $overrides['mimes']));
            $mimes = $narrowed === [] ? $this->mimes : $narrowed;
        }

        return $this->with(
            maxFiles: min($this->maxFiles, max(1, (int) ($overrides['max_files'] ?? $this->maxFiles))),
            maxKb: min($this->maxKb, max(1, (int) ($overrides['max_kb'] ?? $this->maxKb))),
            mimes: $mimes,
        );
    }

    public function withoutExtension(string $extension): self
    {
        return $this->with(mimes: array_values(array_diff($this->mimes, [$extension])));
    }

    public function isStandalone(): bool
    {
        return $this->owner === null;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === MediaVisibility::Private;
    }

    public function allowsExtension(string $extension): bool
    {
        return in_array($extension, $this->mimes, true);
    }

    /**
     * The sniffed MIME types this collection accepts, for the uploader's `accept` attribute.
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        /** @var array<string, string> $map */
        $map = config('media.mime_types');

        return array_keys(array_filter($map, fn (string $extension) => $this->allowsExtension($extension)));
    }

    /**
     * @return array{key: string, label: string, visibility: string, max_files: int, max_kb: int, mimes: list<string>, accept: list<string>, guidance: array{shape: string, width: int, height: int, tip?: string}|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'visibility' => $this->visibility->value,
            'max_files' => $this->maxFiles,
            'max_kb' => $this->maxKb,
            'mimes' => $this->mimes,
            'accept' => $this->mimeTypes(),
            'guidance' => $this->guidance,
        ];
    }

    /**
     * Shape, recommended size and a framing tip, from config/media.php or a page field.
     *
     * @return array{shape: string, width: int, height: int, tip?: string}|null
     */
    public static function guidanceFrom(mixed $guidance): ?array
    {
        if (! is_array($guidance) || ! isset($guidance['shape'], $guidance['width'], $guidance['height'])) {
            return null;
        }

        return array_filter([
            'shape' => (string) $guidance['shape'],
            'width' => max(1, (int) $guidance['width']),
            'height' => max(1, (int) $guidance['height']),
            'tip' => isset($guidance['tip']) ? (string) $guidance['tip'] : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  list<string>|null  $mimes
     */
    private function with(?int $maxFiles = null, ?int $maxKb = null, ?array $mimes = null): self
    {
        return new self(
            key: $this->key,
            label: $this->label,
            owner: $this->owner,
            visibility: $this->visibility,
            maxFiles: $maxFiles ?? $this->maxFiles,
            maxKb: $maxKb ?? $this->maxKb,
            mimes: $mimes ?? $this->mimes,
            conversions: $this->conversions,
            uploadPermission: $this->uploadPermission,
            tokenUpload: $this->tokenUpload,
            systemUpload: $this->systemUpload,
            guidance: $this->guidance,
        );
    }

    private function assertValid(): void
    {
        /** @var array<string, string> $map */
        $map = config('media.mime_types');
        $unknown = array_diff($this->mimes, array_values($map));

        if ($this->mimes === [] || $unknown !== []) {
            throw new LogicException("Media collection [{$this->key}] lists no or unknown extensions: ".implode(', ', $unknown));
        }

        $unknownConversions = array_diff($this->conversions, array_keys((array) config('media.conversions')));

        if ($unknownConversions !== []) {
            throw new LogicException("Media collection [{$this->key}] lists unknown conversions: ".implode(', ', $unknownConversions));
        }

        if ($this->uploadPermission === null && ! $this->tokenUpload && ! $this->systemUpload) {
            throw new LogicException("Media collection [{$this->key}] has no upload rule.");
        }

        if ($this->tokenUpload && $this->owner === null) {
            throw new LogicException("Media collection [{$this->key}] allows token uploads but has no owner.");
        }
    }
}
