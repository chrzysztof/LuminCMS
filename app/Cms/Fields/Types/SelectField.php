<?php

namespace App\Cms\Fields\Types;

use Illuminate\Validation\Rule;

class SelectField extends ChoiceField
{
    public function name(): string
    {
        return 'select';
    }

    public function label(): string
    {
        return 'Select';
    }

    protected function defaults(): array
    {
        return [
            'choices' => [], 'default_value' => '', 'allow_null' => false, 'multiple' => false,
            'ui' => false, 'ajax' => false, 'placeholder' => '', 'return_format' => 'value',
        ];
    }

    public function rules(array $field): array
    {
        return $field['multiple']
            ? [...$this->baseRules($field), 'array']
            : [...$this->baseRules($field), 'string', Rule::in($this->choiceKeys($field))];
    }

    public function itemRules(array $field): array
    {
        return $field['multiple'] ? ['string', Rule::in($this->choiceKeys($field))] : [];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        $list = $this->toStringList($value);

        if ($field['multiple']) {
            return $list === [] ? null : $list;
        }

        return $list[0] ?? null;
    }
}
