<?php

namespace App\Http\Requests\Admin\ContactMessage;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ContactMessage::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ContactMessageStatus::class)],
        ];
    }
}
