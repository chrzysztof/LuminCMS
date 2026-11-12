<?php

namespace App\Cms\Fields\Types;

class RelationshipField extends PostObjectField
{
    public function name(): string
    {
        return 'relationship';
    }

    public function label(): string
    {
        return 'Powiązane treści';
    }

    protected function multiple(array $field): bool
    {
        return true;
    }

    protected function defaults(): array
    {
        $defaults = parent::defaults();
        unset($defaults['multiple'], $defaults['allow_null']);

        return $defaults;
    }
}
