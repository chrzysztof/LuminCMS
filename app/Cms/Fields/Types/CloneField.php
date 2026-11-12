<?php

namespace App\Cms\Fields\Types;

use App\Cms\Fields\AbstractFieldType;

/**
 * Pole, które nie przechowuje własnych danych, tylko wskazuje inne grupy pól lub pojedyncze pola.
 * FieldRegistry::expand() zamienia je przed użyciem na zwykłe pola (seamless) albo grupę (group),
 * więc walidacja, formularz i odczyt w szablonach działają bez znajomości klonów.
 *
 * Wartości `clone`: "klucz_grupy" (cała grupa) albo "klucz_grupy.nazwa_pola" (jedno pole).
 */
class CloneField extends AbstractFieldType
{
    public function name(): string
    {
        return 'clone';
    }

    public function label(): string
    {
        return 'Clone';
    }

    public function category(): string
    {
        return 'layout';
    }

    protected function defaults(): array
    {
        return [
            'clone' => [],
            'display' => 'seamless',   // seamless: pola jak własne | group: pod nazwą klonu jako grupa
            'prefix_label' => false,
            'prefix_name' => false,
        ];
    }

    /** Nierozwinięty klon (np. bez wskazanych źródeł) nic nie zapisuje. */
    public function rules(array $field): array
    {
        return ['nullable'];
    }

    public function normalize(mixed $value, array $field): mixed
    {
        return null;
    }
}
