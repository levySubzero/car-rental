<?php

namespace App\Services\Media;

use App\Exceptions\Domain\MediaException;
use App\Support\Media\ProcessedImage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\EncoderInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Image re-encoding for the Media module (TECHNICAL_PLAN §6.5, D19).
 */
class ImageProcessor
{
    // Extensions this class re-encodes. Anything else (PDF) is stored as uploaded.
    public const IMAGE_EXTENSIONS = ['jpg', 'png', 'webp', 'heic'];

    private const JPEG_QUALITY = 88;

    public function __construct(private readonly ImageDriver $driver) {}

    public function handles(string $extension): bool
    {
        return in_array($extension, self::IMAGE_EXTENSIONS, true);
    }

    /**
     * Re-encode an uploaded original: orientation applied, every metadata block (EXIF, GPS,
     * XMP) dropped, HEIC converted to JPEG. The same format is kept otherwise, so PNG and
     * WebP transparency survive.
     */
    public function sanitize(string $path, string $extension): ProcessedImage
    {
        $image = $this->decode($path);

        [$target, $encoder, $mime] = match ($extension) {
            'png' => ['png', new PngEncoder, 'image/png'],
            'webp' => ['webp', new WebpEncoder(quality: 90, strip: true), 'image/webp'],
            default => ['jpg', new JpegEncoder(quality: self::JPEG_QUALITY, strip: true), 'image/jpeg'],
        };

        return $this->encode($image, $encoder, $target, $mime);
    }

    /**
     * One conversion from config media.conversions: scaled down to the width (never up).
     *
     * @param  array{width: int, format?: string, quality?: int}  $spec
     */
    public function convert(string $contents, array $spec): ProcessedImage
    {
        $image = $this->decode($contents, binary: true)->scaleDown(width: $spec['width']);
        $quality = $spec['quality'] ?? 80;

        return match ($spec['format'] ?? 'webp') {
            'jpg' => $this->encode($image, new JpegEncoder(quality: $quality, strip: true), 'jpg', 'image/jpeg'),
            default => $this->encode($image, new WebpEncoder(quality: $quality, strip: true), 'webp', 'image/webp'),
        };
    }

    private function decode(string $source, bool $binary = false): ImageInterface
    {
        try {
            $manager = $this->driver->manager();

            return $binary ? $manager->decodeBinary($source) : $manager->decodePath($source);
        } catch (Throwable) {
            throw MediaException::unreadableImage();
        }
    }

    private function encode(ImageInterface $image, EncoderInterface $encoder, string $extension, string $mime): ProcessedImage
    {
        return new ProcessedImage(
            contents: $image->encode($encoder)->toString(),
            extension: $extension,
            mimeType: $mime,
            width: $image->width(),
            height: $image->height(),
        );
    }
}
