<?php

namespace App\Cms\Builder;

/**
 * Własne style elementów z edytora wizualnego. HTML dostaje tylko klasę lm-x-{hash}, a reguły trafiają do osobnego CSS-a
 * (css_cache). Dozwolone są wyłącznie wybrane właściwości z walidowanymi wartościami, więc nie da się wstrzyknąć
 * dowolnego CSS-a ani odwołania do zewnętrznych zasobów poza adresem obrazu tła.
 */
class LocalStyles
{
    private const COLOR = '/^(#[0-9a-f]{3,8}|[a-z]{3,20}|(rgb|hsl)a?\(\s*[0-9.,%\s\/deg]+\)|var\(--lm-[a-z0-9-]+\))$/i';

    private const LENGTH = '/^(0|auto|-?\d{1,4}(\.\d{1,3})?(px|rem|em|%|vw|vh))$/';

    private const POSITION = '/^(center|top|bottom|left|right)( (center|top|bottom|left|right))?$/';

    private const IMAGE = '/^(none|url\((["\']?)(\/[^"\'()\s\\\\]*|https?:\/\/[^"\'()\s\\\\]+)\2\))$/';

    private const LENGTH_PROPS = [
        'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'border-radius', 'max-width', 'min-height',
    ];

    /** Urządzenia edytora: media query => klucz. */
    private const MEDIA = ['' => '', '(max-width: 900px)' => 'md', '(max-width: 640px)' => 'sm'];

    /** @var array<string, array<string, array<string, string>>> id elementu => [urządzenie => [właściwość => wartość]] */
    private array $byId = [];

    /** @var array<string, string> klasa => deklaracje (po jednej regule na urządzenie) */
    private array $rules = [];

    /** @param  array<int, mixed>  $styles  project['styles'] z GrapesJS */
    public function load(array $styles): void
    {
        $this->byId = $this->rules = [];

        foreach ($styles as $rule) {
            if (! is_array($rule) || ! empty($rule['state']) || ! empty($rule['selectorsAdd']) || ! empty($rule['atRuleType'])) {
                continue;
            }

            $selectors = (array) ($rule['selectors'] ?? []);
            $media = preg_replace('/\s+/', ' ', trim((string) ($rule['mediaText'] ?? ''))) ?? '';

            if (count($selectors) !== 1 || ! is_string($selectors[0]) || ! isset(self::MEDIA[$media])) {
                continue;
            }

            if (! preg_match('/^#([A-Za-z][A-Za-z0-9_-]{0,40})$/', $selectors[0], $m)) {
                continue;
            }

            $clean = $this->clean((array) ($rule['style'] ?? []));

            if ($clean) {
                $this->byId[$m[1]][self::MEDIA[$media]] = $clean;
            }
        }
    }

    /** Klasa dla elementu o danym id (rejestruje jej reguły) albo null, gdy element nie ma własnych stylów. */
    public function classFor(?string $id): ?string
    {
        if (! $id || ! isset($this->byId[$id])) {
            return null;
        }

        $variants = $this->byId[$id];
        ksort($variants);
        $class = 'lm-x-'.substr(md5(json_encode($variants)), 0, 8);

        foreach ($variants as $device => $declarations) {
            $this->rules[$class][$device] = implode(';', array_map(fn ($p, $v) => "$p:$v", array_keys($declarations), $declarations));
        }

        return $class;
    }

    public function css(): string
    {
        $out = ['' => '', 'md' => '', 'sm' => ''];

        foreach ($this->rules as $class => $devices) {
            foreach ($devices as $device => $body) {
                $out[$device] .= ".$class{{$body}}";
            }
        }

        return $out['']
            .($out['md'] !== '' ? "@media (max-width:900px){{$out['md']}}" : '')
            .($out['sm'] !== '' ? "@media (max-width:640px){{$out['sm']}}" : '');
    }

    /** @return array<string, string> */
    public function clean(array $style): array
    {
        $clean = [];

        foreach ($style as $property => $value) {
            if (! is_string($property) || ! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            $ok = match (true) {
                in_array($property, ['color', 'background-color'], true) => preg_match(self::COLOR, $value) === 1,
                in_array($property, self::LENGTH_PROPS, true) => preg_match(self::LENGTH, $value) === 1,
                $property === 'background-image' => preg_match(self::IMAGE, $value) === 1,
                $property === 'background-size' => in_array($value, ['cover', 'contain', 'auto'], true),
                $property === 'background-position' => preg_match(self::POSITION, $value) === 1,
                $property === 'background-repeat' => in_array($value, ['no-repeat', 'repeat'], true),
                default => false,
            };

            if ($ok) {
                $clean[$property] = $value;
            }
        }

        ksort($clean);

        return $clean;
    }
}
