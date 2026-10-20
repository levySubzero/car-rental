<?php

namespace App\Enums;

/**
 * Where a contact form message is in the inbox (D26). Staff may move between any of them.
 */
enum ContactMessageStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Spam => 'Spam',
        };
    }
}
