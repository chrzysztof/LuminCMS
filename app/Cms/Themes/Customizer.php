<?php

namespace App\Cms\Themes;

use App\Cms\Settings\Settings;

/**
 * Ustawienia wyglądu motywu ("Dostosuj"): wartości tokenów z theme.json zapisane per motyw,
 * zamieniane na zmienne CSS w :root. Zapisujemy i wypisujemy tylko wartości, które przeszły walidację.
 */
class Customizer
{
    public function __construct(private readonly Settings $settings, private readonly ThemeManager $themes) {}

    /** @return list<array<string, mixed>> */
    public function tokens(): array
    {
        return $this->themes->active()->tokens();
    }

    /** Zapisane (zmienione względem domyślnych) wartości aktywnego motywu, tylko poprawne. */
    public function saved(): array
    {
        $all = (array) $this->settings->get('theme_customizer', 'values', []);

        return $this->sanitize((array) ($all[$this->themes->active()->slug] ?? []));
    }

    /** Wartości wszystkich tokenów: zapisane albo domyślne. */
    public function values(): array
    {
        $saved = $this->saved();

        return collect($this->tokens())->mapWithKeys(fn ($t) => [$t['key'] => $saved[$t['key']] ?? $t['default']])->all();
    }

    /** Odrzuca nieznane klucze i niepoprawne wartości; wartości równe domyślnym nie są zapisywane. */
    public function sanitize(array $input): array
    {
        $clean = [];

        foreach ($this->tokens() as $t) {
            $value = $this->normalize($t, $input[$t['key']] ?? null);

            if ($value !== null && $value !== $t['default']) {
                $clean[$t['key']] = $value;
            }
        }

        return $clean;
    }

    /** Zwraca znormalizowaną wartość albo null, gdy jest niepoprawna. */
    public function normalize(array $token, mixed $value): string|float|null
    {
        return match ($token['type']) {
            'color' => is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null,
            'select' => is_string($value) && in_array($value, array_column($token['options'], 'value'), true) ? $value : null,
            'size' => is_numeric($value) && $value >= $token['min'] && $value <= $token['max'] ? round((float) $value, 3) : null,
            default => null,
        };
    }

    public function save(array $input): void
    {
        $all = (array) $this->settings->get('theme_customizer', 'values', []);
        $all[$this->themes->active()->slug] = $this->sanitize($input);

        $this->settings->save('theme_customizer', ['values' => $all]);
    }

    /** Blok CSS ze zmienionymi tokenami (pusty, gdy wszystko jest domyślne). */
    public function css(): string
    {
        $saved = $this->saved();
        $lines = [];

        foreach ($this->tokens() as $t) {
            if (isset($saved[$t['key']])) {
                $lines[] = $t['css'].':'.$this->cssValue($t, $saved[$t['key']]).';';
            }
        }

        return $lines === [] ? '' : ':root{'.implode('', $lines).'}';
    }

    private function cssValue(array $token, string|float $value): string
    {
        return $token['type'] === 'size' ? rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.').$token['unit'] : (string) $value;
    }
}
