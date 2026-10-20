<?php

namespace App\Http\Requests\Admin\Policy;

use App\Enums\PolicyGroup;
use App\Models\Policy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates a policy (details plus its first draft), or updates the details of the one bound
 * to the route. Text changes on an existing policy go through SavePolicyDraftRequest.
 */
class SavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $policy = $this->policy();

        return $policy === null
            ? ($this->user()?->can('create', Policy::class) ?? false)
            : ($this->user()?->can('update', $policy) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('policies', 'slug')->ignore($this->policy())],
            'group' => ['required', Rule::enum(PolicyGroup::class)],
            'summary' => ['nullable', 'string', 'max:500'],
            'show_on_home' => ['required', 'boolean'],
            'body' => [Rule::requiredIf($this->policy() === null), Rule::prohibitedIf($this->policy() !== null), 'string', 'max:50000'],
        ];
    }

    /**
     * @return array{title: string, slug?: string|null, group: string, summary?: string|null, show_on_home?: bool}
     */
    public function details(): array
    {
        /** @var array{title: string, slug?: string|null, group: string, summary?: string|null, show_on_home?: bool} */
        return $this->safe()->except('body');
    }

    private function policy(): ?Policy
    {
        $policy = $this->route('policy');

        return $policy instanceof Policy ? $policy : null;
    }
}
