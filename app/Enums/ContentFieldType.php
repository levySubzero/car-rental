<?php

namespace App\Enums;

/**
 * The kinds of field a preset page can have (D22). Layout is M7's job, so this list stays short.
 */
enum ContentFieldType: string
{
    // One line, or a short paragraph when the field is multiline. Plain text.
    case Text = 'text';
    // Formatted text, rendered to HTML by App\Support\Markdown (D23).
    case Markdown = 'markdown';
    // A media uuid from the `page.image` collection.
    case Image = 'image';
    // A fixed-length list of items with plain-text subfields, e.g. "Why choose us" 01–04.
    case List = 'list';
}
