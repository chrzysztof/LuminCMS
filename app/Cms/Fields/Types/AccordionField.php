<?php

namespace App\Cms\Fields\Types;

class AccordionField extends LayoutElementField
{
    public function name(): string
    {
        return 'accordion';
    }

    public function label(): string
    {
        return 'Accordion';
    }

    protected function defaults(): array
    {
        return ['open' => false, 'multi_expand' => false, 'endpoint' => false];
    }
}
