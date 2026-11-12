<?php

namespace App\Cms\Fields\Types;

use Illuminate\Validation\Rule;

class CheckboxField extends ChoiceField
{
    public function name(): string
    {
        return 'checkbox';
    }

    public function label(): string
    {
        return 'Checkbox';
    }

    protected function defaults(): array
    {
        return [
            'choices' => [], 'default_value' => [], 'allow_custom' => false, 'save_custom' => false,
            'layout' => 'vertical', 'toggle' => false, 'return_format' => 'value',
        ];
    }

    public function rules(array $field): array
    {
        return [...$this->baseRules($field), 'array'];
    }

    /** Przy allow_custom wartością może być dowolny tekst, inaczej tylko jedna z opcji. */
    public function itemRules(array $field): array
    {
        return $field['allow_custom']
            ? ['string', 'max:255']
            : ['string', Rule::in($this->choiceKeys($field))];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        $list = $this->toStringList($value);

        return $list === [] ? null : $list;
    }
}
