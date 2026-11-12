<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;

class EmailField extends AbstractFieldType
{
    public function name(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return 'Email';
    }

    public function category(): string
    {
        return 'basic';
    }

    protected function defaults(): array
    {
        return ['default_value' => '', 'placeholder' => '', 'prepend' => '', 'append' => ''];
    }

    public function rules(array $field): array
    {
        return [...$this->baseRules($field), 'string', 'email'];
    }
}
