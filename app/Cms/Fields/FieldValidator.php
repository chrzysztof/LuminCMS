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

    /** Zbiera reguły walidacji; dla kontenerów (Repeater) schodzi do każdego wiersza z prefiksem "nazwa.indeks.". */
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

            if ($type instanceof ContainerFieldType && is_array($input[$field['name']] ?? null)) {
                foreach ($input[$field['name']] as $index => $row) {
                    if (is_array($row)) {
                        $this->collect($this->visible($type->subFields($field), $row), $row, "{$key}.{$index}.", $rules, $attributes);
                    }
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

            if ($type instanceof ContainerFieldType && is_array($value)) {
                $value = array_map(
                    fn (array $row) => $this->normalize($this->visible($type->subFields($field), $row), $row),
                    array_values(array_filter((array) ($input[$field['name']] ?? []), 'is_array')),
                ) ?: null;
            }

            $values[$field['name']] = $value;
        }

        return $values;
    }
}
