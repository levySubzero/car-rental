<?php

namespace App\Http\Requests\Admin\FxRate;

use App\Models\FxRate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RefreshFxRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('override', FxRate::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['accept_jumps' => ['sometimes', 'boolean']];
    }
}
