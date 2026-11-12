<?php

namespace App\Cms\Fields;

use App\Cms\Content\Content;
use InvalidArgumentException;

class FieldRegistry
{
    /** @var array<string, FieldType> */
    private array $types = [];

    /** @var array<string, array<int, array>> definicje pól grup pasujących do treści (pamięć podręczna na czas żądania) */
    private array $fieldsCache = [];

    public function register(FieldType $type): static
    {
        $this->types[$type->name()] = $type;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function get(string $name): FieldType
    {
        return $this->types[$name]
            ?? throw new InvalidArgumentException("Nieznany typ pola: {$name}");
    }

    /** @return array<string, FieldType> */
    public function all(): array
    {
        return $this->types;
    }

    /** Typy pogrupowane według kategorii, do wyświetlenia w kreatorze pól. */
    public function byCategory(): array
    {
        $grouped = [];

        foreach ($this->types as $type) {
            $grouped[$type->category()][] = $type;
        }

        return $grouped;
    }

    /** Definicja pola uzupełniona o ustawienia domyślne jego typu. */
    public function resolveField(array $field): array
    {
        return $this->get($field['type'] ?? '')->resolve($field);
    }

    /**
     * Wartość pola własnego treści, sformatowana według return_format.
     * Zwraca $default, gdy żadna grupa pasująca do treści nie definiuje takiego pola
     * albo wartość nie została zapisana.
     */
    public function valueFor(Content $content, string $name, mixed $default = null): mixed
    {
        $field = $this->fieldsFor($content)[$name] ?? null;
        $stored = $content->fields[$name] ?? null;

        if ($field === null || $stored === null) {
            return $default;
        }

        return $this->get($field['type'])->format($stored, $field);
    }

    /** @return array<string, array> pola (z ustawieniami domyślnymi) po nazwie */
    private function fieldsFor(Content $content): array
    {
        $key = $content->getKey().'|'.$content->updated_at?->getTimestamp();

        if (! isset($this->fieldsCache[$key])) {
            $fields = [];

            foreach (FieldGroup::forContent($content) as $group) {
                foreach ($group->fields as $definition) {
                    $resolved = $this->resolveField($definition);

                    if ($resolved['name'] !== '') {
                        $fields[$resolved['name']] = $resolved;
                    }
                }
            }

            $this->fieldsCache[$key] = $fields;
        }

        return $this->fieldsCache[$key];
    }
}
