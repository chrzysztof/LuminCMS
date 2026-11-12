<?php

namespace App\Cms\Fields;

/**
 * Typ pola zawierający inne pola (Repeater, a w przyszłości Group i Flexible Content).
 * Walidator schodzi rekurencyjnie do pól podrzędnych, osobno dla każdego wiersza.
 */
interface ContainerFieldType extends FieldType
{
    /** Definicje pól podrzędnych (jeszcze bez ustawień domyślnych typów). */
    public function subFields(array $field): array;
}
