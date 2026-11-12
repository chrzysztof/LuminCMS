<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use App\Cms\Fields\ContainerFieldType;
use App\Cms\Fields\FieldRegistry;

class RepeaterField extends AbstractFieldType implements ContainerFieldType
{
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
        return 'advanced';
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
        return array_values(array_filter((array) ($field['sub_fields'] ?? []), 'is_array'));
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

        $registry = app(FieldRegistry::class);
        $definitions = [];

        foreach ($this->subFields($field) as $definition) {
            $resolved = $registry->resolveField($definition);

            if ($resolved['name'] !== '' && $registry->get($resolved['type'])->storesData()) {
                $definitions[] = $resolved;
            }
        }

        return array_map(function ($row) use ($definitions, $registry) {
            $out = [];

            foreach ($definitions as $sub) {
                $stored = $row[$sub['name']] ?? null;
                $out[$sub['name']] = $stored === null ? null : $registry->get($sub['type'])->format($stored, $sub);
            }

            return $out;
        }, array_values($value));
    }
}
