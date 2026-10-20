<?php

namespace App\Http\Requests\Admin\Service;

use App\Enums\ServiceCtaType;
use App\Models\Service;
use App\Support\Rules\PendingOrOwnedMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creates a service, or updates the one bound to the route. `image` is the uuid of an
 * uploaded file, or null to remove it. `cta_value` is required for a link button.
 */
class SaveServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->service();

        return $service === null
            ? ($this->user()?->can('create', Service::class) ?? false)
            : ($this->user()?->can('update', $service) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('services', 'slug')->ignore($this->service())],
            'summary' => ['required', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:20000'],
            'cta_type' => ['required', Rule::enum(ServiceCtaType::class)],
            'cta_value' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf($this->input('cta_type') === ServiceCtaType::Link->value),
                Rule::when($this->input('cta_type') === ServiceCtaType::Link->value, ['url:https,http']),
            ],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'uuid', new PendingOrOwnedMedia('service.image', $this->service())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cta_type' => 'button',
            'cta_value' => $this->input('cta_type') === ServiceCtaType::Link->value ? 'link URL' : 'WhatsApp message',
        ];
    }

    /**
     * A book button has no value, so a leftover one is dropped.
     *
     * @return array{title: string, slug?: string|null, summary: string, body?: string|null, cta_type: string, cta_value?: string|null, is_active?: bool, image?: string|null}
     */
    public function serviceData(): array
    {
        /** @var array{title: string, slug?: string|null, summary: string, body?: string|null, cta_type: string, cta_value?: string|null, is_active?: bool, image?: string|null} $data */
        $data = $this->validated();

        if ($data['cta_type'] === ServiceCtaType::Book->value) {
            $data['cta_value'] = null;
        }

        return $data;
    }

    private function service(): ?Service
    {
        $service = $this->route('service');

        return $service instanceof Service ? $service : null;
    }
}
