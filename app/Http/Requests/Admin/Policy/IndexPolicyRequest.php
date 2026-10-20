<?php

namespace App\Http\Requests\Admin\Policy;

use App\Models\Policy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Policy::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
