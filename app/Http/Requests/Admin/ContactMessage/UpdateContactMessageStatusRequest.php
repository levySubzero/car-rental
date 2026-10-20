<?php

namespace App\Http\Requests\Admin\ContactMessage;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactMessageStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ContactMessage $message */
        $message = $this->route('contact_message');

        return $this->user()?->can('update', $message) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ContactMessageStatus::class)],
        ];
    }
}
