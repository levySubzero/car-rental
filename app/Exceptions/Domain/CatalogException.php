<?php

namespace App\Exceptions\Domain;

class CatalogException extends DomainException
{
    public static function brandInUse(int $cars): self
    {
        return new self("This brand is used by {$cars} car(s), including deleted ones. Move them to another brand first.");
    }

    public static function carTypeInUse(int $cars): self
    {
        return new self("This car type is used by {$cars} car(s), including deleted ones. Move them to another type first.");
    }

    public static function notPublishable(string $reason): self
    {
        return new self("This car cannot be published yet: {$reason}.");
    }

    public static function blackoutOverlaps(): self
    {
        return new self('This period overlaps another blackout for the same car.');
    }
}
