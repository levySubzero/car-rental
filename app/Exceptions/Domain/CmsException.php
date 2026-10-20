<?php

namespace App\Exceptions\Domain;

class CmsException extends DomainException
{
    public static function unresolvedConfirm(): self
    {
        return new self('This policy still has a [CONFIRM: …] note. Resolve the wording and remove the note before publishing.');
    }

    public static function nothingToPublish(): self
    {
        return new self('There is no draft to publish. Edit the policy text first.');
    }

    public static function versionPublished(): self
    {
        return new self('Published policy versions cannot be changed. Save a new draft instead.');
    }
}
