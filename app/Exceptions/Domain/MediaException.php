<?php

namespace App\Exceptions\Domain;

class MediaException extends DomainException
{
    protected function errorField(): ?string
    {
        return 'file';
    }

    public static function unknownCollection(string $key): self
    {
        return new self("Unknown media collection [{$key}].");
    }

    public static function wrongOwner(): self
    {
        return new self('These files cannot be attached here.');
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function typeNotAllowed(array $allowed): self
    {
        return new self('This file type is not allowed. Use '.strtoupper(implode(', ', $allowed)).'.');
    }

    public static function tooLarge(string $limit): self
    {
        return new self("This file is too large. The limit is {$limit}.");
    }

    public static function tooManyPixels(): self
    {
        return new self('This image is too large to process. Resize it and try again.');
    }

    public static function unreadableImage(): self
    {
        return new self('This image could not be read. It may be damaged; try another file.');
    }

    public static function tooManyFiles(int $max): self
    {
        return new self("You can add at most {$max} file(s) here.");
    }

    public static function notAvailable(): self
    {
        return new self('One of these files has expired or is not available. Upload it again.');
    }

    public static function crossVisibility(): self
    {
        return new self('Files cannot be moved between public and private disks.');
    }
}
