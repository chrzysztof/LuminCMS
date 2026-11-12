<?php

namespace App\Cms\Fields;

/**
 * Kontrakt typu pola własnego. Każdy typ (text, select, repeater...) to jedna klasa.
 * Rozszerzenia mogą rejestrować własne typy w FieldRegistry.
 */
interface FieldType
{
    /** Identyfikator typu zapisywany w definicji pola, np. "text". */
    public function name(): string;

    /** Etykieta typu w panelu (klucz tłumaczenia lub tekst). */
    public function label(): string;

    /** basic | content | choice | relational | advanced | layout */
    public function category(): string;

    /** false dla elementów układu (message, accordion, tab), które nie przechowują wartości. */
    public function storesData(): bool;

    /** Definicja pola uzupełniona o ustawienia domyślne typu i ustawienia wspólne. */
    public function resolve(array $field): array;

    /** Reguły walidacji Laravela dla wartości pola. */
    public function rules(array $field): array;

    /** Reguły walidacji dla każdego elementu, gdy wartością jest tablica (wielokrotny wybór). */
    public function itemRules(array $field): array;

    /** Wartość z formularza do postaci zapisywanej w bazie. */
    public function normalize(mixed $value, array $field): mixed;

    /** Wartość z bazy do postaci zwracanej w szablonach (zgodnie z return_format). */
    public function format(mixed $value, array $field): mixed;

    public function isEmpty(mixed $value): bool;
}
