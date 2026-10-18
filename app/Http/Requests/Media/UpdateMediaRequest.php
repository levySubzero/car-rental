<?php

namespace App\Http\Requests\Media;

use App\Models\Media;
use App\Services\Media\CurrentUploader;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /media/{uuid}: alt text and caption.
 */
class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentUploader::class)->allows('update', $this->media());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'alt' => ['sometimes', 'nullable', 'string', 'max:250'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function media(): Media
    {
        /** @var Media */
        return $this->route('media');
    }
}
