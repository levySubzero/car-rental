<?php

namespace App\Http\Requests\Admin\Integrations;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Where to send the test emails, and optionally the booking to build them from.
 */
class SendTestMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::IntegrationsManage) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'reference' => ['nullable', 'string', 'max:30', Rule::exists('bookings', 'reference')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reference.exists' => 'No booking has this reference.'];
    }
}
