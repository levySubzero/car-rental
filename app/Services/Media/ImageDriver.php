<?php

namespace App\Services\Media;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;

/**
 * Picks the intervention/image driver (config media.image_driver) and reports what it can decode.
 * Registered as a singleton in AppServiceProvider.
 */
class ImageDriver
{
    private ?DriverInterface $driver = null;

    private ?ImageManager $manager = null;

    public function name(): string
    {
        $configured = config('media.image_driver');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return extension_loaded('imagick') ? 'imagick' : 'gd';
    }

    /**
     * Decoding applies the EXIF orientation; encoding strips all metadata (D19).
     */
    public function manager(): ImageManager
    {
        return $this->manager ??= new ImageManager($this->driver(), autoOrientation: true, strip: true);
    }

    public function supports(string $extension): bool
    {
        return $this->driver()->supports($extension);
    }

    private function driver(): DriverInterface
    {
        return $this->driver ??= $this->name() === 'imagick' ? new ImagickDriver : new GdDriver;
    }
}
