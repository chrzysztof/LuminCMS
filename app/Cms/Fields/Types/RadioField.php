<?php

namespace App\Cms\Fields\Types;

use Illuminate\Validation\Rule;

class RadioField extends ChoiceField
{
    public function name(): string
    {
        return 'radio';
    }

    public function label(): string
    {
        return 'Radio Button';
    }

    protected function defaults(): array
    {
        return [
            'choices' => [], 'default_value' => '', 'allow_null' => false, 'other_choice' => false,
            'save_other_choice' => false, 'layout' => 'vertical', 'return_format' => 'value',
        ];
    }

    /** Przy other_choice dozwolona jest dowolna wartość, inaczej tylko jedna z opcji. */
    public function rules(array $field): array
    {
        return $field['other_choice']
            ? [...$this->baseRules($field), 'string', 'max:255']
            : [...$this->baseRules($field), 'string', Rule::in($this->choiceKeys($field))];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return $this->toStringList($value)[0] ?? null;
    }
}
