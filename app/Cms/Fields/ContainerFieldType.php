<?php

namespace App\Cms\Fields;

/**
 * Typ pola zawierający inne pola (Repeater, Group, Flexible Content).
 * Walidator schodzi rekurencyjnie do pól podrzędnych, osobno dla każdego wpisu (wiersza).
 */
interface ContainerFieldType extends FieldType
{
    /** Wszystkie definicje pól podrzędnych (jeszcze bez ustawień domyślnych typów). */
    public function subFields(array $field): array;

    /**
     * Wpisy (wiersze) zapisane w wartości, każdy z własną listą pól.
     * Repeater: wiersz = wpis. Group: jeden wpis (ścieżka pusta). Flexible Content: wpis z układem w `extra`.
     *
     * @return list<array{path: string, row: array, fields: array, extra: array}>
     */
    public function entries(array $field, mixed $value): array;

    /**
     * Składa wartość do zapisu z przetworzonych wpisów (w kolejności z entries()).
     *
     * @param  list<array{extra: array, values: array}>  $entries
     */
    public function assemble(array $field, array $entries): mixed;

    /**
     * Pola podrzędne dla ścieżki nazw (RelationController): zwraca [pola, reszta ścieżki].
     * Flexible Content zjada pierwszy segment jako nazwę układu.
     *
     * @param  list<string>  $path
     * @return array{0: array, 1: list<string>}
     */
    public function descend(array $field, array $path): array;
}
