<?php

namespace App\Http\Requests\Admin\Car;

use App\Models\Car;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Car::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'integer'],
            'type' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:published,draft'],
            'available' => ['nullable', 'in:yes,no'],
            'featured' => ['nullable', 'in:yes,no'],
            'sort' => ['nullable', 'in:name,daily_rate_minor,updated_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
