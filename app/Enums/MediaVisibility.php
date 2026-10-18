<?php

namespace App\Enums;

/**
 * Set by the collection, never by the request (TECHNICAL_PLAN §6.1).
 */
enum MediaVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    /**
     * The disk new uploads of this visibility are written to.
     */
    public function disk(): string
    {
        return (string) config("media.disks.{$this->value}");
    }
}
