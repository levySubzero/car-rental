<?php

namespace App\Http\Requests\Admin\Policy;

use App\Models\Policy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SavePolicyDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Policy $policy */
        $policy = $this->route('policy');

        return $this->user()?->can('update', $policy) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:50000'],
        ];
    }
}
