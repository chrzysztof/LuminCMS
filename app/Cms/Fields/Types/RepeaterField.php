<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use App\Cms\Fields\ContainerFieldType;
use App\Cms\Fields\FormatsSubFields;

class RepeaterField extends AbstractFieldType implements ContainerFieldType
{
    use FormatsSubFields;

    /** Twardy limit wierszy, żeby jedno żądanie nie mogło zapisać nieograniczonej liczby danych. */
    public const MAX_ROWS = 200;

    public function name(): string
    {
        return 'repeater';
    }

    public function label(): string
    {
        return 'Repeater';
    }

    public function category(): string
    {
        return 'layout';
    }

    protected function defaults(): array
    {
        return [
            'sub_fields' => [],
            'min' => '', 'max' => '',
            'layout' => 'block',            // table | block | row
            'button_label' => __('Dodaj wiersz'),
            'collapsed' => '',              // nazwa pola podrzędnego używanego jako tytuł zwiniętego wiersza
        ];
    }

    public function subFields(array $field): array
    {
        return $this->listOfArrays($field['sub_fields'] ?? []);
    }

    public function entries(array $field, mixed $value): array
    {
        $entries = [];

        foreach (is_array($value) ? $value : [] as $index => $row) {
            if (is_array($row)) {
                $entries[] = ['path' => (string) $index, 'row' => $row, 'fields' => $this->subFields($field), 'extra' => []];
            }
        }

        return $entries;
    }

    public function assemble(array $field, array $entries): mixed
    {
        return array_map(fn (array $e) => $e['values'], $entries) ?: null;
    }

    public function descend(array $field, array $path): array
    {
        return [$this->subFields($field), $path];
    }

    public function rules(array $field): array
    {
        $rules = [...$this->baseRules($field), 'array'];
        $max = $this->number($field, 'max');
        $rules[] = 'max:'.($max !== null ? min($max, self::MAX_ROWS) : self::MAX_ROWS);

        if (($min = $this->number($field, 'min')) !== null) {
            $rules[] = "min:{$min}";
        }

        return $rules;
    }

    public function itemRules(array $field): array
    {
        return ['array'];
    }

    /** Wiersze są normalizowane przez FieldValidator (pole po polu); tu tylko porządkujemy listę. */
    public function normalize(mixed $value, array $field): mixed
    {
        $rows = array_values(array_filter((array) $value, 'is_array'));

        return $rows ?: null;
    }

    /** Lista wierszy; wartości pól podrzędnych sformatowane jak w zwykłych polach. */
    public function format(mixed $value, array $field): mixed
    {
        if (! is_array($value)) {
            return [];
        }

        return array_map(fn ($row) => $this->formatRow(is_array($row) ? $row : [], $this->subFields($field)), array_values($value));
    }
}
