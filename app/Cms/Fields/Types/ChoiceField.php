<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;
use Illuminate\Support\Arr;

/**
 * Wspólna baza pól z listą opcji. Opcje to tablica wartość => etykieta,
 * a return_format wybiera, co zwraca format(): value | label | array.
 */
abstract class ChoiceField extends AbstractFieldType
{
    public function category(): string
    {
        return 'choice';
    }

    /** Dozwolone wartości jako teksty (klucze liczbowe PHP zamieniane na string). */
    protected function choiceKeys(array $field): array
    {
        return array_map('strval', array_keys($field['choices'] ?? []));
    }

    /** Wartość lub tablica wartości jako lista niepustych tekstów. */
    protected function toStringList(mixed $value): array
    {
        return array_values(array_map(
            'strval',
            array_filter(Arr::wrap($value), fn ($v) => $v !== null && $v !== '')
        ));
    }

    public function format(mixed $value, array $field): mixed
    {
        if ($value === null) {
            return null;
        }

        $one = function (string $v) use ($field) {
            $label = $field['choices'][$v] ?? $v;

            return match ($field['return_format']) {
                'label' => $label,
                'array' => ['value' => $v, 'label' => $label],
                default => $v,
            };
        };

        return is_array($value)
            ? array_map(fn ($v) => $one((string) $v), $value)
            : $one((string) $value);
    }
}
