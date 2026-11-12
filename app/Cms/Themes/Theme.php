<?php

namespace App\Cms\Themes;

/** Motyw z folderu themes/{slug}: manifest theme.json, widoki i zasoby. */
class Theme
{
    public function __construct(
        public readonly string $slug,
        public readonly string $path,
        public readonly array $manifest,
    ) {}

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
    public function templates(): array
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

    /** Adres zasobu motywu (z parametrem wersji, żeby przeglądarka odświeżała zmieniony plik). */
    public function asset(string $file): string
    {
        $full = $this->assetsPath().'/'.$file;
        $version = is_file($full) ? filemtime($full) : 0;

        return url("theme-assets/{$this->slug}/{$file}").($version ? "?v={$version}" : '');
    }
}
