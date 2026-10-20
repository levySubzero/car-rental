<?php

namespace App\Http\Requests\Admin\Service;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Service::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
