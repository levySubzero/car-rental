<?php

namespace App\Enums;

/**
 * State of a file's image conversions (ProcessMediaJob).
 */
enum MediaProcessing: string
{
    case Queued = 'queued';
    case Done = 'done';

    // Conversions failed; URLs fall back to the original.
    case Failed = 'failed';

    // Not an image, or the collection has no conversions.
    case Skipped = 'skipped';
}
