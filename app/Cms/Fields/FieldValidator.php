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
        $visible = [];

        foreach ($fields as $definition) {
            $field = $this->registry->resolveField($definition);

            if ($field['name'] !== '' && $this->registry->get($field['type'])->storesData() && $this->logic->passes($field, $input)) {
                $visible[] = $field;
            }
        }

        $rules = [];
        $attributes = [];

        foreach ($visible as $field) {
            $type = $this->registry->get($field['type']);
            $name = $field['name'];

            $rules[$name] = $type->rules($field);

            if ($item = $type->itemRules($field)) {
                $rules["{$name}.*"] = $item;
            }

            $attributes[$name] = $field['label'] !== '' ? $field['label'] : $name;
        }

        Validator::make($input, $rules, [], $attributes)->validate();

        $values = [];

        foreach ($visible as $field) {
            $values[$field['name']] = $this->registry->get($field['type'])
                ->normalize($input[$field['name']] ?? null, $field);
        }

        return $values;
    }
}
