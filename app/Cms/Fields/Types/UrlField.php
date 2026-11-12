<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;

class UrlField extends AbstractFieldType
{
    public function name(): string
    {
        return 'url';
    }

    public function label(): string
    {
        return 'URL';
    }

    public function category(): string
    {
        return 'basic';
    }

    protected function defaults(): array
    {
        return ['default_value' => '', 'placeholder' => ''];
    }

    /** Dozwolone: http://, https:// oraz adresy względne protokołu (//host). */
    public function rules(array $field): array
    {
        return [...$this->baseRules($field), 'string', 'regex:/^(https?:)?\/\/\S+$/i'];
    }
}
