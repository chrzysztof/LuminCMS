<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;

class NumberField extends AbstractFieldType
{
    public function name(): string
    {
        return 'number';
    }

    public function label(): string
    {
        return 'Number';
    }

    public function category(): string
    {
        return 'basic';
    }

    protected function defaults(): array
    {
        return [
            'default_value' => '', 'placeholder' => '', 'prepend' => '', 'append' => '',
            'min' => '', 'max' => '', 'step' => '',
        ];
    }

    public function rules(array $field): array
    {
        $rules = [...$this->baseRules($field), 'numeric'];

        if (($min = $this->number($field, 'min')) !== null) {
            $rules[] = "min:{$min}";
        }

        if (($max = $this->number($field, 'max')) !== null) {
            $rules[] = "max:{$max}";
        }

        return $rules;
    }

    /** Liczba całkowita zapisywana jako int, pozostałe jako float. */
    public function normalize(mixed $value, array $field): mixed
    {
        $value = parent::normalize($value, $field);

        return $value === null ? null : $value + 0;
    }
}
