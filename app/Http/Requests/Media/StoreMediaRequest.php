<?php

namespace App\Http\Requests\Media;

use App\Models\Media;
use App\Services\Media\CurrentUploader;
use App\Services\Media\MediaCollectionRegistry;
use App\Support\Media\MediaCollection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * POST /media: one file into one collection. MediaService re-checks the sniffed type
 * and size; these rules give the uploader a field error first.
 */
class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $collection = $this->collection();

        // An unknown collection fails validation (422) rather than authorization.
        if ($collection === null) {
            return true;
        }

        // Checked here as well as in MediaPolicy: Gate::before lets super admins past
        // the policy, and system files (vouchers) are never uploaded over HTTP.
        return ! $collection->systemUpload
            && app(CurrentUploader::class)->allows('upload', [Media::class, $collection]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $registry = app(MediaCollectionRegistry::class);
        $collection = $this->collection();

        return [
            'collection' => ['required', 'string', Rule::in(array_keys($registry->all()))],
            'file' => array_filter([
                'required',
                'file',
                $collection ? 'max:'.$collection->maxKb : null,
                $collection ? 'mimetypes:'.implode(',', $collection->mimeTypes()) : null,
            ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $collection = $this->collection();

        return $collection === null ? [] : [
            'file.mimetypes' => 'This file type is not allowed. Use '.strtoupper(implode(', ', $collection->mimes)).'.',
            'file.max' => 'This file is too large. The limit is :max KB.',
        ];
    }

    public function collection(): ?MediaCollection
    {
        $key = $this->input('collection');
        $registry = app(MediaCollectionRegistry::class);

        return is_string($key) && $registry->has($key) ? $registry->get($key) : null;
    }

    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('file');
    }
}
