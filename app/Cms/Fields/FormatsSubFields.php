<?php

namespace App\Cms\Fields;

/** Wspólne formatowanie wartości pól podrzędnych (return_format każdego pola) dla kontenerów. */
trait FormatsSubFields
{
    /** @param  array<int, array>  $subFields  definicje pól podrzędnych */
    protected function formatRow(array $row, array $subFields): array
    {
        $registry = app(FieldRegistry::class);
        $out = [];

        foreach ($subFields as $definition) {
            $sub = $registry->resolveField($definition);

            if ($sub['name'] === '' || ! $registry->get($sub['type'])->storesData()) {
                continue;
            }

            $stored = $row[$sub['name']] ?? null;
            $out[$sub['name']] = $stored === null ? null : $registry->get($sub['type'])->format($stored, $sub);
        }

        return $out;
    }

    /** @return array<int, array> */
    protected function listOfArrays(mixed $items): array
    {
        return array_values(array_filter((array) $items, 'is_array'));
    }
}
