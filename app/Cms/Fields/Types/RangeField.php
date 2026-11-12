<?php

namespace App\Cms\Fields\Types;

class RangeField extends NumberField
{
    public function name(): string
    {
        return 'range';
    }

    public function label(): string
    {
        return 'Range';
    }

    protected function defaults(): array
    {
        return [
            'default_value' => '', 'prepend' => '', 'append' => '',
            'min' => 0, 'max' => 100, 'step' => 1,
        ];
    }
}
