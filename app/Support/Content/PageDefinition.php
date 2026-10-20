<?php

namespace App\Support\Content;

use App\Enums\ContentFieldType;

/**
 * One preset page (config/pages.php): its slug, admin title and fields.
 */
final readonly class PageDefinition
{
    /**
     * @param  list<ContentField>  $fields
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public array $fields,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     */
    public static function fromConfig(string $slug, array $definition): self
    {
        $fields = (array) ($definition['fields'] ?? []);

        return new self(
            slug: $slug,
            title: (string) ($definition['title'] ?? $slug),
            description: (string) ($definition['description'] ?? ''),
            fields: array_map(
                fn (string $key, array $field) => ContentField::fromConfig($key, $field),
                array_keys($fields),
                $fields,
            ),
        );
    }

    /**
     * Content with every field present: stored values for known fields, empty values for the
     * rest. Keys that are not fields (left over from a removed field) are dropped.
     *
     * @param  iterable<string, mixed>|null  $content
     * @return array<string, mixed>
     */
    public function fill(?iterable $content): array
    {
        $stored = $content === null ? [] : (is_array($content) ? $content : iterator_to_array($content));
        $filled = [];

        foreach ($this->fields as $field) {
            $filled[$field->key] = $this->normalise($field, $stored[$field->key] ?? null);
        }

        return $filled;
    }

    /**
     * Labels of the required fields that are still empty.
     *
     * @param  iterable<string, mixed>|null  $content
     * @return list<string>
     */
    public function missing(?iterable $content): array
    {
        $filled = $this->fill($content);

        return array_values(array_map(
            fn (ContentField $field) => $field->label,
            array_filter($this->fields, fn (ContentField $field) => $field->required && $field->isEmpty($filled[$field->key])),
        ));
    }

    /**
     * The media uuids the image fields point at.
     *
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public function imageUuids(array $content): array
    {
        $uuids = [];

        foreach ($this->fields as $field) {
            if ($field->type === ContentFieldType::Image && is_string($content[$field->key] ?? null)) {
                $uuids[] = $content[$field->key];
            }
        }

        return array_values(array_unique($uuids));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'fields' => array_map(fn (ContentField $field) => $field->toArray(), $this->fields),
        ];
    }

    private function normalise(ContentField $field, mixed $value): mixed
    {
        if ($field->type !== ContentFieldType::List) {
            $value = is_string($value) ? trim($value) : null;

            return $value === '' ? null : $value;
        }

        $items = is_array($value) ? array_values($value) : [];
        $normalised = [];

        for ($index = 0; $index < $field->items; $index++) {
            $item = is_array($items[$index] ?? null) ? $items[$index] : [];

            foreach ($field->fields as $sub) {
                $normalised[$index][$sub->key] = $this->normalise($sub, $item[$sub->key] ?? null);
            }
        }

        return $normalised;
    }
}
