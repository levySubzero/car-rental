<?php

namespace App\Http\Requests\Admin\Brand;

use App\Models\Brand;
use App\Support\Rules\PendingOrOwnedMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates a brand, or updates the brand bound to the route. `logo` is the uuid of an
 * uploaded file, or null to remove the logo.
 */
class SaveBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        $brand = $this->brand();

        return $brand === null
            ? ($this->user()?->can('create', Brand::class) ?? false)
            : ($this->user()?->can('update', $brand) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('brands', 'name')->ignore($this->brand())],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($this->brand())],
            'show_in_marquee' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'logo' => ['nullable', 'uuid', new PendingOrOwnedMedia('brand.logo', $this->brand())],
        ];
    }

    private function brand(): ?Brand
    {
        $brand = $this->route('brand');

        return $brand instanceof Brand ? $brand : null;
    }
}
