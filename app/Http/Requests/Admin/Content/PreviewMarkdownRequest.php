<?php

namespace App\Http\Requests\Admin\Content;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Renders Markdown for the editor's Preview tab, so staff see exactly what the site will show.
 */
class PreviewMarkdownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAny([
            Permission::ServicesCreate,
            Permission::ServicesUpdate,
            Permission::PagesUpdate,
            Permission::PoliciesCreate,
            Permission::PoliciesUpdate,
            Permission::PaymentMethodsUpdate,
        ]) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'markdown' => ['present', 'nullable', 'string', 'max:50000'],
        ];
    }
}
