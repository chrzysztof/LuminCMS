<?php

namespace App\Cms\Builder;

use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Themes\ThemeManager;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Własne bloki edytora zdefiniowane przez aktywny motyw: themes/{slug}/blocks/{nazwa}/
 *   block.json         etykieta, kategoria, ikona i pola (te same typy pól co w polach własnych)
 *   render.blade.php   HTML bloku ($attrs: sformatowane wartości pól, $block: definicja)
 *   style.css          opcjonalne style (łączone w /theme-blocks.css)
 *
 * W edytorze blok zapisuje tylko nazwę i wartości pól. W HTML trafia shortcode [block], który
 * jest zamieniany na markup przy każdym wyświetleniu, więc zmiana szablonu bloku działa od razu.
 */
class BlockRegistry
{
    /** @var array<string, array>|null */
    private ?array $blocks = null;

    private ?string $forTheme = null;

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly FieldRegistry $fields,
        private readonly FieldValidator $validator,
    ) {}

    /** @return array<string, array{name: string, label: string, category: string, icon: string, fields: list<array>, dir: string}> */
    public function all(): array
    {
        $theme = $this->themes->active();

        if ($this->blocks !== null && $this->forTheme === $theme->slug) {
            return $this->blocks;
        }

        $this->forTheme = $theme->slug;
        $this->blocks = [];

        foreach (glob($theme->path.'/blocks/*/block.json') ?: [] as $file) {
            $dir = dirname($file);
            $name = basename($dir);
            $manifest = json_decode((string) file_get_contents($file), true);

            if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) || ! is_array($manifest) || ! is_file($dir.'/render.blade.php')) {
                continue;
            }

            $fields = [];

            foreach ((array) ($manifest['fields'] ?? []) as $definition) {
                try {
                    if (is_array($definition) && ($definition['name'] ?? '') !== '') {
                        $fields[] = $this->translated($this->fields->resolveField($definition));
                    }
                } catch (Throwable) {
                    // Nieznany typ pola: pomijamy pole, reszta bloku działa.
                }
            }

            $this->blocks[$name] = [
                'name' => $name,
                'label' => __((string) ($manifest['label'] ?? $name)),
                'category' => __((string) ($manifest['category'] ?? 'Motyw')),
                'icon' => is_string($manifest['icon'] ?? null) && ! preg_match('/[<>]\s*(script|iframe)/i', $manifest['icon']) ? $manifest['icon'] : '',
                'fields' => $fields,
                'dir' => $dir,
            ];
        }

        return $this->blocks;
    }

    /** Teksty interfejsu pola (etykiety, instrukcje, opcje wyboru, pola wierszy) przechodzą przez tłumaczenia panelu. */
    private function translated(array $field): array
    {
        foreach (['label', 'instructions', 'placeholder', 'button_label'] as $key) {
            if (isset($field[$key]) && is_string($field[$key]) && $field[$key] !== '') {
                $field[$key] = __($field[$key]);
            }
        }

        if (is_array($field['choices'] ?? null)) {
            $field['choices'] = array_map(fn ($c) => is_string($c) ? __($c) : $c, $field['choices']);
        }

        if (is_array($field['sub_fields'] ?? null)) {
            $field['sub_fields'] = array_map(fn ($f) => is_array($f) ? $this->translated($f) : $f, $field['sub_fields']);
        }

        return $field;
    }

    public function get(string $name): ?array
    {
        return $this->all()[$name] ?? null;
    }

    /** Definicje dla edytora (bez ścieżek) wraz z wartościami domyślnymi pól. */
    public function definitions(): array
    {
        return array_values(array_map(fn (array $b) => [
            'name' => $b['name'],
            'label' => $b['label'],
            'category' => $b['category'],
            'icon' => $b['icon'],
            'fields' => $b['fields'],
            'defaults' => $this->defaults($b),
        ], $this->all()));
    }

    private function defaults(array $block): array
    {
        $defaults = [];

        foreach ($block['fields'] as $field) {
            if ($this->fields->get($field['type'])->storesData() && ($field['default_value'] ?? '') !== '' && ! is_array($field['default_value'])) {
                $defaults[$field['name']] = $field['default_value'];
            }
        }

        return $defaults;
    }

    /**
     * Zostawia tylko znane pola o poprawnych wartościach (każde sprawdzane osobno, więc jedno błędne nie psuje reszty).
     *
     * @return array<string, mixed>
     */
    public function normalize(string $name, array $attrs): array
    {
        $block = $this->get($name);

        if (! $block) {
            return [];
        }

        $clean = [];

        foreach ($block['fields'] as $field) {
            if (! $this->fields->get($field['type'])->storesData() || ! array_key_exists($field['name'], $attrs)) {
                continue;
            }

            try {
                $result = $this->validator->validate([$field], [$field['name'] => $attrs[$field['name']]]);
            } catch (ValidationException) {
                continue;
            }

            if (array_key_exists($field['name'], $result)) {
                $clean[$field['name']] = $result[$field['name']];
            }
        }

        return $clean;
    }

    /** Wartości w postaci dla szablonu (według return_format pól). */
    public function formatted(array $block, array $attrs): array
    {
        $out = [];

        foreach ($block['fields'] as $field) {
            if ($this->fields->get($field['type'])->storesData()) {
                $value = $attrs[$field['name']] ?? null;
                $out[$field['name']] = $value === null ? null : $this->fields->get($field['type'])->format($value, $field);
            }
        }

        return $out;
    }

    public function render(string $name, array $attrs): string
    {
        $block = $this->get($name);

        if (! $block) {
            return '';
        }

        try {
            return trim(View::file($block['dir'].'/render.blade.php', [
                'attrs' => $this->formatted($block, $this->normalize($name, $attrs)),
                'block' => $block,
            ])->render());
        } catch (Throwable $e) {
            // Błąd w szablonie bloku nie może wyłączać całej strony.
            report($e);

            return '';
        }
    }

    /** Style wszystkich bloków aktywnego motywu. */
    public function css(): string
    {
        $css = '';

        foreach ($this->all() as $block) {
            if (is_file($block['dir'].'/style.css')) {
                $css .= file_get_contents($block['dir'].'/style.css')."\n";
            }
        }

        return trim($css);
    }

    /** Dane bloku w atrybucie shortcode: JSON w base64url (bez znaków specjalnych shortcode'ów). */
    public static function encode(array $attrs): string
    {
        return rtrim(strtr(base64_encode(json_encode($attrs, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    }

    public static function decode(string $data): array
    {
        $json = base64_decode(strtr($data, '-_', '+/'), true);
        $attrs = $json === false ? null : json_decode($json, true);

        return is_array($attrs) ? $attrs : [];
    }
}
