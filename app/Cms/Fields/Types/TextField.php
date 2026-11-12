<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;

class TextField extends AbstractFieldType
{
    public function name(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return 'Text';
    }

    public function category(): string
    {
        return 'basic';
    }

    protected function defaults(): array
    {
        return ['default_value' => '', 'placeholder' => '', 'prepend' => '', 'append' => '', 'maxlength' => ''];
    }

    public function rules(array $field): array
    {
        $rules = [...$this->baseRules($field), 'string'];

        if (($max = $this->number($field, 'maxlength')) && $max > 0) {
            $rules[] = "max:{$max}";
        }

        return $rules;
    }
}
