<?php

namespace App\Cms\Fields\Types;

use Illuminate\Validation\Rule;

class ButtonGroupField extends ChoiceField
{
    public function name(): string
    {
        return 'button_group';
    }

    public function label(): string
    {
        return 'Button Group';
    }

    protected function defaults(): array
    {
        return [
            'choices' => [], 'default_value' => '', 'allow_null' => false,
            'layout' => 'horizontal', 'return_format' => 'value',
        ];
    }

    public function rules(array $field): array
    {
        return [...$this->baseRules($field), 'string', Rule::in($this->choiceKeys($field))];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return $this->toStringList($value)[0] ?? null;
    }
}
