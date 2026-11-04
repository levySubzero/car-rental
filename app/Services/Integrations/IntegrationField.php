<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationFieldType;

/**
 * One value on the admin Integrations page and the config path(s) it overrides.
 */
final readonly class IntegrationField
{
    /**
     * @param  list<string>  $paths  the first one holds the env value shown as the fallback
     * @param  array<string, string>  $options  value => label, for a choice
     * @param  list<string>  $rules  extra validation rules
     */
    public function __construct(
        public string $key,
        public string $label,
        public IntegrationFieldType $type,
        public array $paths,
        public array $options = [],
        public ?string $description = null,
        public array $rules = [],
    ) {}

    public function isSecret(): bool
    {
        return $this->type === IntegrationFieldType::Secret;
    }
}
