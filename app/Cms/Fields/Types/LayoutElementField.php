<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;

/** Baza elementów układu formularza (message, accordion, tab), które nie przechowują wartości. */
abstract class LayoutElementField extends AbstractFieldType
{
    public function category(): string
    {
        return 'layout';
    }

    public function storesData(): bool
    {
        return false;
    }

    public function rules(array $field): array
    {
        return [];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return null;
    }
}
