<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;

class TrueFalseField extends AbstractFieldType
{
    public function name(): string
    {
        return 'true_false';
    }

    public function label(): string
    {
        return 'True / False';
    }

    public function category(): string
    {
        return 'choice';
    }

    protected function defaults(): array
    {
        return ['message' => '', 'default_value' => false, 'ui' => false, 'ui_on_text' => '', 'ui_off_text' => ''];
    }

    /** Pole wymagane oznacza, że przełącznik musi być włączony. */
    public function rules(array $field): array
    {
        return $field['required'] ? ['accepted'] : ['nullable', 'boolean'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function format(mixed $value, array $field): mixed
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function isEmpty(mixed $value): bool
    {
        return $value === null || $value === false;
    }
}
