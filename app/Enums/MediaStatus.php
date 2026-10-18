<?php

namespace App\Enums;

/**
 * Lifecycle of a media row. Conversion progress is tracked separately (MediaProcessing, D18).
 */
enum MediaStatus: string
{
    // Uploaded but not yet saved with a form; removed by media:cleanup once expires_at passes.
    case Pending = 'pending';

    // Belongs to an owner, or is claimed by a standalone collection.
    case Attached = 'attached';
}
