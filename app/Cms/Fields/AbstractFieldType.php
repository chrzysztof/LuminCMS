<?php

namespace App\Cms\Fields;

abstract class AbstractFieldType implements FieldType
{
    /** Ustawienia wspólne dla wszystkich typów pól. */
    private const COMMON_DEFAULTS = [
        'label' => '',
        'name' => '',
        'instructions' => '',
        'required' => false,
        'conditional_logic' => false,
        'wrapper' => ['width' => '', 'class' => '', 'id' => ''],
    ];

    /** Ustawienia domyślne specyficzne dla typu. */
    protected function defaults(): array
    {
        return [];
    }

    public function storesData(): bool
    {
        return true;
    }

    final public function resolve(array $field): array
    {
        return array_replace_recursive(self::COMMON_DEFAULTS, $this->defaults(), $field, ['type' => $this->name()]);
    }

    /** Podstawa reguł: wymagane albo opcjonalne. */
    protected function baseRules(array $field): array
    {
        return [$field['required'] ? 'required' : 'nullable'];
    }

    public function rules(array $field): array
    {
        return $this->baseRules($field);
    }

    public function itemRules(array $field): array
    {
        return [];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return $this->isEmpty($value) ? null : $value;
    }

    public function format(mixed $value, array $field): mixed
    {
        return $value;
    }

    public function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** Ustawienie liczbowe: zwraca liczbę albo null, gdy puste lub niepoprawne. */
    protected function number(array $field, string $key): int|float|null
    {
        $v = $field[$key] ?? null;

        return is_numeric($v) ? $v + 0 : null;
    }
}
