<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use App\Cms\Fields\ContainerFieldType;
use App\Cms\Fields\FormatsSubFields;

/** Grupa pól zapisana pod jedną nazwą jako obiekt (np. `adres.ulica`, `adres.miasto`). */
class GroupField extends AbstractFieldType implements ContainerFieldType
{
    use FormatsSubFields;

    public function name(): string
    {
        return 'group';
    }

    public function label(): string
    {
        return 'Group';
    }

    public function category(): string
    {
        return 'layout';
    }

    protected function defaults(): array
    {
        return ['sub_fields' => [], 'layout' => 'block']; // block | table | row
    }

    public function subFields(array $field): array
    {
        return $this->listOfArrays($field['sub_fields'] ?? []);
    }

    /** Zawsze jeden wpis, także bez danych, żeby wymagane pola podrzędne były sprawdzane. */
    public function entries(array $field, mixed $value): array
    {
        return [['path' => '', 'row' => is_array($value) ? $value : [], 'fields' => $this->subFields($field), 'extra' => []]];
    }

    public function assemble(array $field, array $entries): mixed
    {
        $values = $entries[0]['values'] ?? [];

        return array_filter($values, fn ($v) => $v !== null) === [] ? null : $values;
    }

    public function descend(array $field, array $path): array
    {
        return [$this->subFields($field), $path];
    }

    /** Wymagalność grupy wynika z jej pól podrzędnych. */
    public function rules(array $field): array
    {
        return ['nullable', 'array'];
    }

    public function itemRules(array $field): array
    {
        return [];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return is_array($value) ? $value : null;
    }

    public function isEmpty(mixed $value): bool
    {
        return ! is_array($value) || array_filter($value, fn ($v) => $v !== null && $v !== '' && $v !== []) === [];
    }

    public function format(mixed $value, array $field): mixed
    {
        return $this->formatRow(is_array($value) ? $value : [], $this->subFields($field));
    }
}
