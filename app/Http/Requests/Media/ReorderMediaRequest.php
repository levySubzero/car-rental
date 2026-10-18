<?php

namespace App\Http\Requests\Media;

use App\Models\Media;
use App\Services\Media\CurrentUploader;
use App\Services\Media\MediaCollectionRegistry;
use App\Support\Media\MediaCollection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /media/reorder: {collection, owner_type, owner_id, uuids[]}. Standalone
 * collections send no owner.
 */
class ReorderMediaRequest extends FormRequest
{
    private ?Model $resolvedOwner = null;

    public function authorize(): bool
    {
        $collection = $this->collection();

        if ($collection === null) {
            return true; // fails validation instead
        }

        return app(CurrentUploader::class)->allows('reorder', [Media::class, $collection, $this->owner()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'collection' => ['required', 'string', Rule::in(array_keys(app(MediaCollectionRegistry::class)->all()))],
            'owner_type' => ['nullable', 'string', 'max:64'],
            'owner_id' => ['nullable', 'required_with:owner_type', 'integer'],
            'uuids' => ['required', 'array', 'max:100'],
            'uuids.*' => ['required', 'uuid', 'distinct'],
        ];
    }

    public function collection(): ?MediaCollection
    {
        $key = $this->input('collection');
        $registry = app(MediaCollectionRegistry::class);

        return is_string($key) && $registry->has($key) ? $registry->get($key) : null;
    }

    /**
     * The owner named by owner_type (a morph alias) and owner_id. Only the alias the
     * collection is registered for is looked up; anything else 404s.
     */
    public function owner(): ?Model
    {
        $type = $this->input('owner_type');

        if (! is_string($type) || $type === '') {
            return null;
        }

        $class = Relation::getMorphedModel($type);
        $id = $this->input('owner_id');
        abort_if($class === null || $type !== $this->collection()?->owner || ! is_scalar($id), 404);

        /** @var class-string<Model> $class */
        return $this->resolvedOwner ??= $class::query()->whereKey($id)->firstOrFail();
    }

    /**
     * @return list<string>
     */
    public function uuids(): array
    {
        return array_values(array_map('strval', (array) $this->validated('uuids')));
    }
}
