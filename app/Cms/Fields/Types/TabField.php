<?php

namespace App\Cms\Fields\Types;

class TabField extends LayoutElementField
{
    public function name(): string
    {
        return 'tab';
    }

    public function label(): string
    {
        return 'Tab';
    }

    protected function defaults(): array
    {
        return ['placement' => 'top', 'endpoint' => false];
    }
}
