<?php

namespace App\Exceptions\Domain;

class MediaException extends DomainException
{
    public static function unknownCollection(string $key): self
    {
        return new self("Unknown media collection [{$key}].");
    }

    public static function wrongOwner(string $key): self
    {
        return new self('These files cannot be attached here.');
    }
}
