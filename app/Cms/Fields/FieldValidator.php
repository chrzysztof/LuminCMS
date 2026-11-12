<?php

namespace App\Cms\Fields;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Waliduje wartości pól według definicji grupy i zwraca je w postaci do zapisu.
 * Pola ukryte przez logikę warunkową nie są walidowane ani zwracane.
 */
class FieldValidator
{
    public function __construct(
        private readonly FieldRegistry $registry,
        private readonly ConditionalLogic $logic,
    ) {}

    /**
     * @param  array<int, array>  $fields  definicje pól grupy
     * @param  array<string, mixed>  $input  wartości z formularza po nazwie pola
     * @return array<string, mixed> znormalizowane wartości po nazwie pola
     *
     * @throws ValidationException
     */
    public function validate(array $fields, array $input): array
    {
        $fields = $this->registry->expand($fields);
        $visible = $this->visible($fields, $input);
        $rules = [];
        $attributes = [];

        $this->collect($visible, $input, '', $rules, $attributes);

        Validator::make($input, $rules, [], $attributes)->validate();

        return $this->normalize($visible, $input);
    }

    /**
     * Pola z nazwą, przechowujące dane i niewyłączone przez logikę warunkową (z ustawieniami domyślnymi typu).
     *
     * @return array<int, array>
     */
    private function visible(array $fields, array $input): array
    {
        $visible = [];

        foreach ($fields as $definition) {
            $field = $this->registry->resolveField($definition);

            if ($field['name'] !== '' && $this->registry->get($field['type'])->storesData() && $this->logic->passes($field, $input)) {
                $visible[] = $field;
            }
        }

        return $visible;
    }

    /** Zbiera reguły walidacji; dla kontenerów schodzi do każdego wpisu z prefiksem "nazwa.indeks." (Group: "nazwa."). */
    private function collect(array $visible, array $input, string $prefix, array &$rules, array &$attributes): void
    {
        foreach ($visible as $field) {
            $type = $this->registry->get($field['type']);
            $key = $prefix.$field['name'];

            $rules[$key] = $type->rules($field);

            if ($item = $type->itemRules($field)) {
                $rules["{$key}.*"] = $item;
            }

            $attributes[$key] = $field['label'] !== '' ? $field['label'] : $field['name'];

            if ($type instanceof ContainerFieldType) {
                foreach ($type->entries($field, $input[$field['name']] ?? null) as $entry) {
                    $childPrefix = $entry['path'] === '' ? "{$key}." : "{$key}.{$entry['path']}.";
                    $this->collect($this->visible($entry['fields'], $entry['row']), $entry['row'], $childPrefix, $rules, $attributes);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function normalize(array $visible, array $input): array
    {
        $values = [];

        foreach ($visible as $field) {
            $type = $this->registry->get($field['type']);
            $value = $type->normalize($input[$field['name']] ?? null, $field);

            if ($type instanceof ContainerFieldType) {
                $value = $type->assemble($field, array_map(
                    fn (array $e) => ['extra' => $e['extra'], 'values' => $this->normalize($this->visible($e['fields'], $e['row']), $e['row'])],
                    $type->entries($field, $input[$field['name']] ?? null),
                ));
            }

            $values[$field['name']] = $value;
        }

        return $values;
    }
}
