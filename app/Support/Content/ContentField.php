<?php

namespace App\Support\Content;

use App\Enums\ContentFieldType;
use App\Support\Media\MediaCollection;

/**
 * One field of a preset page (config/pages.php).
 */
final readonly class ContentField
{
    /**
     * @param  list<ContentField>  $fields  subfields of a list
     * @param  array{shape: string, width: int, height: int, tip?: string}|null  $guidance  image fields: replaces the page.image collection's
     */
    public function __construct(
        public string $key,
        public ContentFieldType $type,
        public string $label,
        public ?string $section = null,
        public ?string $help = null,
        public bool $required = false,
        public int $max = 255,
        public bool $multiline = false,
        public int $items = 0,
        public array $fields = [],
        public ?array $guidance = null,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromConfig(string $key, array $definition): self
    {
        $type = ContentFieldType::from((string) ($definition['type'] ?? 'text'));

        return new self(
            key: $key,
            type: $type,
            label: (string) ($definition['label'] ?? $key),
            section: isset($definition['section']) ? (string) $definition['section'] : null,
            help: isset($definition['help']) ? (string) $definition['help'] : null,
            required: (bool) ($definition['required'] ?? false),
            max: (int) ($definition['max'] ?? ($type === ContentFieldType::Markdown ? 20000 : 255)),
            multiline: (bool) ($definition['multiline'] ?? false),
            items: $type === ContentFieldType::List ? max(1, (int) ($definition['items'] ?? 1)) : 0,
            fields: $type === ContentFieldType::List
                ? array_map(
                    fn (string $sub, array $field) => self::fromConfig($sub, ['type' => 'text', ...$field]),
                    array_keys((array) ($definition['fields'] ?? [])),
                    (array) ($definition['fields'] ?? []),
                )
                : [],
            guidance: $type === ContentFieldType::Image ? MediaCollection::guidanceFrom($definition['guidance'] ?? null) : null,
        );
    }

    /**
     * The value an untouched field holds: null, or a list of empty items.
     */
    public function emptyValue(): mixed
    {
        if ($this->type !== ContentFieldType::List) {
            return null;
        }

        return array_fill(0, $this->items, array_fill_keys(array_map(fn (self $field) => $field->key, $this->fields), null));
    }

    public function isEmpty(mixed $value): bool
    {
        if ($this->type !== ContentFieldType::List) {
            return blank($value);
        }

        if (! is_array($value) || count($value) < $this->items) {
            return true;
        }

        foreach ($value as $item) {
            foreach ($this->fields as $field) {
                if (blank(is_array($item) ? ($item[$field->key] ?? null) : null)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'label' => $this->label,
            'section' => $this->section,
            'help' => $this->help,
            'required' => $this->required,
            'max' => $this->max,
            'multiline' => $this->multiline,
            'items' => $this->items,
            'fields' => array_map(fn (self $field) => $field->toArray(), $this->fields),
            'guidance' => $this->guidance,
        ];
    }
}
