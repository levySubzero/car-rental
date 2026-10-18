<?php

namespace App\Support\Media;

/**
 * Re-encoded image bytes ready to store (ImageProcessor).
 */
final readonly class ProcessedImage
{
    public function __construct(
        public string $contents,
        public string $extension,
        public string $mimeType,
        public int $width,
        public int $height,
    ) {}
}
