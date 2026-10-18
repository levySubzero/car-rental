<?php

namespace App\Services\Media;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Interfaces\DriverInterface;

/**
 * Picks the intervention/image driver (config media.image_driver) and reports what it can decode.
 */
class ImageDriver
{
    private ?DriverInterface $driver = null;

    public function name(): string
    {
        $configured = config('media.image_driver');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return extension_loaded('imagick') ? 'imagick' : 'gd';
    }

    public function driver(): DriverInterface
    {
        return $this->driver ??= $this->name() === 'imagick' ? new ImagickDriver : new GdDriver;
    }

    public function supports(string $extension): bool
    {
        return $this->driver()->supports($extension);
    }
}
