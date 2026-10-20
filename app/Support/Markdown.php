<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Content is stored as Markdown (D23): policy, page and service bodies and payment instructions.
 * It is rendered here, server side, so public pages and the admin preview show the same HTML.
 * Raw HTML in the source is stripped and javascript:/data: links are dropped, so the
 * output is safe to inject without a separate sanitiser.
 */
final class Markdown
{
    public static function toHtml(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        return trim(Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]));
    }

    /**
     * A literal TODO marks content the client still has to supply (D26).
     */
    public static function hasTodo(?string $text): bool
    {
        return $text !== null && preg_match('/\bTODO\b/', $text) === 1;
    }

    /**
     * An unresolved [CONFIRM: …] reviewer note. A policy carrying one cannot be published (D24).
     */
    public static function hasConfirm(?string $text): bool
    {
        return $text !== null && str_contains($text, '[CONFIRM');
    }
}
