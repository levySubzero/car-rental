<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A copy of one email the app sent (System → Email log). Written by LogSentMail, never edited.
 *
 * @property int $id
 * @property string|null $mailer
 * @property string $to
 * @property string|null $cc
 * @property string|null $subject
 * @property string|null $notification
 * @property string|null $html
 * @property string|null $text
 * @property list<array{name: string, mime: string, size: int}>|null $attachments
 * @property CarbonImmutable $created_at
 */
class MailLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'html' => 'encrypted',
            'text' => 'encrypted',
            'attachments' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
