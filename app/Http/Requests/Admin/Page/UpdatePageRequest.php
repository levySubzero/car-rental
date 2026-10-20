<?php

namespace App\Http\Requests\Admin\Page;

use App\Models\Page;
use App\Support\Content\PageRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Saves a preset page. The `content` rules come from the page's definition in config/pages.php.
 */
class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->page()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $registry = app(PageRegistry::class);
        $definition = $registry->get($this->page()->slug) ?? abort(404);

        return [
            ...$registry->rulesFor($definition, $this->page()),
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $definition = app(PageRegistry::class)->get($this->page()->slug);
        $attributes = [];

        foreach ($definition->fields ?? [] as $field) {
            $attributes["content.{$field->key}"] = $field->label;

            foreach ($field->fields as $sub) {
                $attributes["content.{$field->key}.*.{$sub->key}"] = mb_strtolower($sub->label);
            }
        }

        return $attributes;
    }

    private function page(): Page
    {
        /** @var Page */
        return $this->route('page');
    }
}
