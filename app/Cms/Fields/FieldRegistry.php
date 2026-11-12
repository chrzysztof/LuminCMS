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

    /**
     * Rozwija pola typu Clone (także zagnieżdżone w Group, Repeater i Flexible Content) do zwykłych pól:
     * "seamless" wstawia pola źródłowe w miejsce klonu (z opcjonalnymi prefiksami), "group" owija je w grupę.
     * Cykle (klon wskazujący grupę, która klonuje pierwszą) i głębokość są ograniczone.
     *
     * @param  array<int, array>  $fields
     * @param  list<string>  $stack  klucze grup, przez które już przeszliśmy
     * @return array<int, array>
     */
    public function expand(array $fields, array $stack = []): array
    {
        $out = [];

        foreach ($fields as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            if (($definition['type'] ?? '') === 'clone') {
                array_push($out, ...$this->expandClone($definition, $stack));

                continue;
            }

            if (is_array($definition['sub_fields'] ?? null)) {
                $definition['sub_fields'] = $this->expand($definition['sub_fields'], $stack);
            }

            if (is_array($definition['layouts'] ?? null)) {
                $definition['layouts'] = array_map(fn ($layout) => is_array($layout) && is_array($layout['sub_fields'] ?? null)
                    ? ['sub_fields' => $this->expand($layout['sub_fields'], $stack)] + $layout
                    : $layout, $definition['layouts']);
            }

            $out[] = $definition;
        }

        return $out;
    }

    /** @return array<int, array> */
    private function expandClone(array $definition, array $stack): array
    {
        $clone = $this->resolveField($definition);
        $source = [];

        foreach ((array) $clone['clone'] as $reference) {
            [$groupKey, $fieldName] = array_pad(explode('.', (string) $reference, 2), 2, null);

            if (count($stack) >= 5 || in_array($groupKey, $stack, true) || ! ($group = FieldGroup::where('key', $groupKey)->first())) {
                continue;
            }

            $fields = $this->expand($group->fields ?? [], [...$stack, $groupKey]);

            if ($fieldName !== null) {
                $fields = array_values(array_filter($fields, fn ($f) => ($f['name'] ?? null) === $fieldName));
            }

            array_push($source, ...$fields);
        }

        if ($clone['display'] === 'group' && $clone['name'] !== '') {
            return [[
                'type' => 'group', 'name' => $clone['name'], 'label' => $clone['label'], 'instructions' => $clone['instructions'],
                'required' => false, 'conditional_logic' => $clone['conditional_logic'], 'wrapper' => $clone['wrapper'],
                'layout' => 'block', 'sub_fields' => $source,
            ]];
        }

        $namePrefix = ! empty($clone['prefix_name']) && $clone['name'] !== '' ? $clone['name'].'_' : '';
        $labelPrefix = ! empty($clone['prefix_label']) && $clone['label'] !== '' ? $clone['label'].' ' : '';

        if ($namePrefix === '' && $labelPrefix === '') {
            return $source;
        }

        $renames = [];

        foreach ($source as $field) {
            if (($field['name'] ?? '') !== '') {
                $renames[$field['name']] = $namePrefix.$field['name'];
            }
        }

        return array_map(function (array $field) use ($namePrefix, $labelPrefix, $renames) {
            if (($field['name'] ?? '') !== '') {
                $field['name'] = $namePrefix.$field['name'];
            }

            if (($field['label'] ?? '') !== '') {
                $field['label'] = $labelPrefix.$field['label'];
            }

            // Warunki odwołujące się do sąsiednich pól klonu wskazują teraz ich nowe nazwy.
            if (is_array($field['conditional_logic'] ?? null)) {
                $field['conditional_logic'] = array_map(fn ($and) => array_map(
                    fn ($rule) => isset($rule['field'], $renames[$rule['field']]) ? ['field' => $renames[$rule['field']]] + $rule : $rule,
                    (array) $and,
                ), $field['conditional_logic']);
            }

            return $field;
        }, $source);
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
                foreach ($this->expand($group->fields ?? []) as $definition) {
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
