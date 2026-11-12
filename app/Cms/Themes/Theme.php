<?php

namespace App\Cms\Themes;

/** Motyw z folderu themes/{slug}: manifest theme.json, widoki i zasoby. */
class Theme
{
    /** Motyw nadrzędny (manifest "parent"), ustawiany przez ThemeManager. */
    public ?Theme $parent = null;

    /** Motyw domyślny: ostatnia deska ratunku dla widoków i zasobów, których brakuje. */
    public ?Theme $fallback = null;

    /** Powód, dla którego motyw nie może być aktywny (np. brak motywu nadrzędnego), albo null. */
    public ?string $error = null;

    public function __construct(
        public readonly string $slug,
        public readonly string $path,
        public readonly array $manifest,
    ) {}

    public function parentSlug(): ?string
    {
        $parent = $this->manifest['parent'] ?? null;

        return is_string($parent) && preg_match('/^[a-z0-9][a-z0-9_-]*$/', $parent) ? $parent : null;
    }

    /** Ten motyw i jego motywy nadrzędne (od najbliższego do najdalszego). @return list<Theme> */
    public function lineage(): array
    {
        $chain = [];

        for ($theme = $this; $theme && count($chain) < 6; $theme = $theme->parent) {
            $chain[] = $theme;
        }

        return $chain;
    }

    /** Katalogi widoków w kolejności szukania: motyw, rodzice, motyw domyślny. @return list<string> */
    public function viewPaths(): array
    {
        $themes = [...$this->lineage(), ...($this->fallback ? [$this->fallback] : [])];

        return array_values(array_unique(array_map(fn (Theme $t) => $t->viewsPath(), $themes)));
    }

    public function version(): string
    {
        return (string) ($this->manifest['version'] ?? '');
    }

    public function description(): string
    {
        return (string) ($this->manifest['description'] ?? '');
    }

    public function author(): string
    {
        return (string) ($this->manifest['author'] ?? '');
    }

    /** Scala listy z manifestów łańcucha dziedziczenia: pozycje motywu potomnego nadpisują rodzica o tym samym kluczu. */
    private function merged(string $method, string $idKey): array
    {
        $out = [];

        foreach (array_reverse($this->lineage()) as $theme) {
            foreach ($theme->{$method}() as $item) {
                $out[$item[$idKey]] = $item;
            }
        }

        return array_values($out);
    }

    public function name(): string
    {
        return (string) ($this->manifest['name'] ?? $this->slug);
    }

    public function viewsPath(): string
    {
        return $this->path.'/views';
    }

    public function assetsPath(): string
    {
        return $this->path.'/assets';
    }

    /** @return list<array{key: string, label: string, types: list<string>}> */
    public function ownTemplates(): array
    {
        return collect($this->manifest['templates'] ?? [])
            ->filter(fn ($t) => is_array($t) && preg_match('/^[a-z0-9_-]+$/', (string) ($t['key'] ?? '')))
            ->map(fn ($t) => [
                'key' => $t['key'],
                'label' => (string) ($t['label'] ?? $t['key']),
                'types' => array_values((array) ($t['types'] ?? [])),
            ])
            ->values()
            ->all();
    }

    /**
     * Tokeny ustawień wyglądu (Dostosuj): color (#rrggbb), select (wartość z options), size (liczba z jednostką).
     *
     * @return list<array<string, mixed>>
     */
    public function ownTokens(): array
    {
        $tokens = [];

        foreach ((array) ($this->manifest['tokens'] ?? []) as $t) {
            if (! is_array($t) || ! preg_match('/^[a-z0-9_]+$/', (string) ($t['key'] ?? '')) || ! preg_match('/^--[a-z0-9-]+$/', (string) ($t['css'] ?? ''))) {
                continue;
            }

            $base = [
                'key' => $t['key'],
                'group' => (string) ($t['group'] ?? 'Ogólne'),
                'label' => (string) ($t['label'] ?? $t['key']),
                'type' => $t['type'] ?? '',
                'css' => $t['css'],
            ];

            if ($base['type'] === 'color' && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($t['default'] ?? ''))) {
                $tokens[] = $base + ['default' => strtolower($t['default'])];
            } elseif ($base['type'] === 'select') {
                $options = collect($t['options'] ?? [])
                    ->filter(fn ($o) => is_array($o) && is_string($o['value'] ?? null) && ! preg_match('/[<>{};]/', $o['value']))
                    ->map(fn ($o) => ['value' => $o['value'], 'label' => (string) ($o['label'] ?? $o['value'])])
                    ->values()->all();

                if ($options !== [] && in_array($t['default'] ?? null, array_column($options, 'value'), true)) {
                    $tokens[] = $base + ['default' => $t['default'], 'options' => $options];
                }
            } elseif ($base['type'] === 'size' && is_numeric($t['default'] ?? null) && is_numeric($t['min'] ?? null) && is_numeric($t['max'] ?? null)) {
                $tokens[] = $base + [
                    'default' => (float) $t['default'], 'min' => (float) $t['min'], 'max' => (float) $t['max'],
                    'step' => max(0.01, (float) ($t['step'] ?? 1)), 'unit' => in_array($t['unit'] ?? 'rem', ['rem', 'px', 'em', '%'], true) ? ($t['unit'] ?? 'rem') : 'rem',
                ];
            }
        }

        return $tokens;
    }

    /** @return list<array{slot: string, label: string}> sloty części globalnych (np. header, footer) */
    public function ownParts(): array
    {
        return $this->keyed('parts', 'slot');
    }

    /** @return list<array{key: string, label: string}> lokalizacje menu zadeklarowane przez motyw */
    public function ownMenuLocations(): array
    {
        return array_map(fn ($l) => ['key' => $l['key'], 'label' => $l['label']], $this->keyed('menus', 'key'));
    }

    /** @return list<array<string, string>> */
    private function keyed(string $section, string $idKey): array
    {
        return collect($this->manifest[$section] ?? [])
            ->filter(fn ($t) => is_array($t) && preg_match('/^[a-z0-9_-]+$/', (string) ($t[$idKey] ?? '')))
            ->map(fn ($t) => [$idKey => $t[$idKey], 'label' => (string) ($t['label'] ?? $t[$idKey])])
            ->values()
            ->all();
    }

    /** @return list<array{key: string, label: string, types: list<string>}> */
    public function templates(): array
    {
        return $this->merged('ownTemplates', 'key');
    }

    /** @return list<array<string, mixed>> */
    public function tokens(): array
    {
        return $this->merged('ownTokens', 'key');
    }

    /** @return list<array{slot: string, label: string}> */
    public function parts(): array
    {
        return $this->merged('ownParts', 'slot');
    }

    /** @return list<array{key: string, label: string}> */
    public function menuLocations(): array
    {
        return $this->merged('ownMenuLocations', 'key');
    }

    /** Adres zasobu motywu (z parametrem wersji, żeby przeglądarka odświeżała zmieniony plik). */
    public function asset(string $file): string
    {
        // Plik bierzemy z pierwszego motywu w łańcuchu (potem z domyślnego), który go ma.
        foreach ([...$this->lineage(), ...($this->fallback ? [$this->fallback] : [])] as $theme) {
            $full = $theme->assetsPath().'/'.$file;

            if (is_file($full)) {
                return url("theme-assets/{$theme->slug}/{$file}").'?v='.filemtime($full);
            }
        }

        return url("theme-assets/{$this->slug}/{$file}");
    }
}
