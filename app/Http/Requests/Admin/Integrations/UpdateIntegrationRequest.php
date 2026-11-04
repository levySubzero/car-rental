<?php

namespace App\Http\Requests\Admin\Integrations;

use App\Enums\Integration;
use App\Enums\IntegrationFieldType;
use App\Enums\Permission;
use App\Services\Integrations\IntegrationConfig;
use App\Services\Integrations\IntegrationField;
use App\Services\Integrations\IntegrationService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::IntegrationsManage) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'credentials' => ['present', 'array'],
            'clear' => ['sometimes', 'array'],
            'clear.*' => ['string', Rule::in(array_map(fn (IntegrationField $field) => $field->key, $this->integration()->fields()))],
        ];

        foreach ($this->integration()->fields() as $field) {
            $rules["credentials.{$field->key}"] = $this->rulesFor($field);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->integration()->fields() as $field) {
            $attributes["credentials.{$field->key}"] = mb_strtolower($field->label);
        }

        return $attributes;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (app(IntegrationConfig::class)->isUnreadable()) {
                $validator->errors()->add('credentials', 'Saved keys cannot be read with the current APP_KEY, so nothing can be saved until it is restored.');

                return;
            }

            $service = app(IntegrationService::class);
            $next = $service->merged($this->integration(), $this->credentials(), $this->clear());

            foreach ($service->problems($this->integration(), $next) as $key => $message) {
                $validator->errors()->add("credentials.{$key}", $message);
            }
        }];
    }

    public function integration(): Integration
    {
        /** @var Integration */
        return $this->route('integration');
    }

    /**
     * @return array<string, mixed>
     */
    public function credentials(): array
    {
        return (array) $this->input('credentials', []);
    }

    /**
     * @return list<string>
     */
    public function clear(): array
    {
        return array_values((array) $this->input('clear', []));
    }

    /**
     * @return list<mixed>
     */
    private function rulesFor(IntegrationField $field): array
    {
        return match ($field->type) {
            IntegrationFieldType::Choice => ['nullable', 'string', Rule::in(array_column(app(IntegrationService::class)->options($field, app(IntegrationService::class)->current($this->integration(), $field)), 'value'))],
            IntegrationFieldType::Toggle => ['nullable', 'boolean'],
            IntegrationFieldType::List => ['nullable', 'string', 'max:2000', $this->addresses(...)],
            default => ['nullable', 'string', 'max:2000', ...$field->rules],
        };
    }

    /**
     * A comma-separated list of IP addresses or CIDR ranges.
     */
    private function addresses(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $address) {
            if (! $this->isAddress($address)) {
                $fail("{$address} is not an IP address or CIDR range.");

                return;
            }
        }
    }

    private function isAddress(string $address): bool
    {
        [$ip, $mask] = array_pad(explode('/', $address, 2), 2, null);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return $mask === null || (ctype_digit($mask) && (int) $mask <= (str_contains($ip, ':') ? 128 : 32));
    }
}
