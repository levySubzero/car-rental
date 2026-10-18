<?php

namespace App\Support\Rules;

use App\Models\Media;
use App\Services\Media\CurrentUploader;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * One media uuid that may be saved into this collection: a pending upload by the
 * current uploader, or a file already attached to $owner (null owner: a standalone
 * collection). Pass null as the owner when creating a new record.
 *
 *     'gallery.*' => ['uuid', new PendingOrOwnedMedia('car.gallery', $this->route('car'))],
 */
class PendingOrOwnedMedia implements ValidationRule
{
    public function __construct(
        private readonly string $collection,
        private readonly ?Model $owner = null,
        private readonly ?Model $actor = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $media = is_string($value)
            ? Media::query()->where('uuid', $value)->where('collection', $this->collection)->first()
            : null;

        // Media routes fill CurrentUploader; admin forms fall back to the signed-in user.
        $actor = $this->actor ?? app(CurrentUploader::class)->actor() ?? auth()->user();

        if ($media === null || $actor === null || ! $media->isAttachableTo($this->owner, $actor)) {
            $fail('This file has expired or is not available. Upload it again.');
        }
    }
}
