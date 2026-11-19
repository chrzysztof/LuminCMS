<?php

namespace App\Cms\Fonts;

use App\Cms\Settings\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Czcionki zaimportowane z Google Fonts. Pliki woff2 są pobierane na serwer i serwowane lokalnie,
 * więc odwiedzający nie łączą się z serwerami Google (prywatność, RODO, szybkość).
 */
class FontLibrary
{
    public const WEIGHTS = [300, 400, 500, 600, 700, 800];

    /** Polskie znaki są w latin-ext. */
    private const SUBSETS = ['latin', 'latin-ext'];

    private const FALLBACK = "system-ui, -apple-system, 'Segoe UI', sans-serif";

    private const MAX_FILE_BYTES = 2_000_000;

    public function __construct(private readonly Settings $settings)
    {
        $settings->define('fonts', ['items' => []]);
    }

    /** @return list<array{family: string, slug: string, weights: list<int>, css: string, stack: string}> */
    public function all(): array
    {
        $items = [];

        foreach ((array) $this->settings->get('fonts', 'items', []) as $item) {
            if (is_array($item) && isset($item['family'], $item['slug'], $item['css']) && $this->validFamily((string) $item['family'])) {
                $items[] = [
                    'family' => (string) $item['family'],
                    'slug' => (string) $item['slug'],
                    'weights' => array_values(array_map('intval', (array) ($item['weights'] ?? []))),
                    'css' => (string) $item['css'],
                    'stack' => $this->stack((string) $item['family']),
                ];
            }
        }

        return $items;
    }

    /** Wszystkie reguły @font-face (przeglądarka pobiera tylko używane pliki). */
    public function css(): string
    {
        return implode('', array_column($this->all(), 'css'));
    }

    /** @return list<array{value: string, label: string}> opcje dla pola "Krój pisma" w Dostosuj */
    public function options(): array
    {
        return array_map(fn ($f) => ['value' => $f['stack'], 'label' => $f['family'].' (Google Fonts)'], $this->all());
    }

    public function stack(string $family): string
    {
        return "'{$family}', ".self::FALLBACK;
    }

    public function validFamily(string $family): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9 ]{1,58}$/', $family);
    }

    /**
     * @param  list<int|string>  $weights
     * @return array{family: string, slug: string, weights: list<int>, css: string, stack: string}
     */
    public function import(string $family, array $weights): array
    {
        $family = trim(preg_replace('/\s+/', ' ', $family) ?? '');

        if (! $this->validFamily($family)) {
            throw new FontException(__('Podaj nazwę czcionki z Google Fonts, np. Poppins.'));
        }

        $weights = collect($weights)->map(fn ($w) => (int) $w)->filter(fn ($w) => in_array($w, self::WEIGHTS, true))->unique()->sort()->values()->all() ?: [400, 700];

        [$sheet, $weights] = $this->fetchSheet($family, $weights);
        $faces = $this->parse($sheet);

        if ($faces === []) {
            throw new FontException(__('Nie znaleziono w Google Fonts czcionki „:name”.', ['name' => $family]));
        }

        $slug = Str::slug($family);
        $disk = Storage::disk('public');
        $css = '';

        foreach ($faces as $face) {
            $file = "fonts/{$slug}/".sha1($face['url']).'.woff2';

            if (! $disk->exists($file)) {
                $disk->put($file, $this->download($face['url']));
            }

            $css .= "@font-face{font-family:'{$family}';font-style:normal;font-weight:{$face['weight']};font-display:swap;src:url('/storage/{$file}') format('woff2');"
                .($face['range'] !== '' ? "unicode-range:{$face['range']};" : '').'}';
        }

        $item = ['family' => $family, 'slug' => $slug, 'weights' => $weights, 'css' => $css];
        $items = array_values(array_filter((array) $this->settings->get('fonts', 'items', []), fn ($i) => ($i['slug'] ?? null) !== $slug));
        $items[] = $item;
        $this->settings->save('fonts', ['items' => $items]);

        return $item + ['stack' => $this->stack($family)];
    }

    public function remove(string $slug): void
    {
        $items = array_values(array_filter((array) $this->settings->get('fonts', 'items', []), fn ($i) => ($i['slug'] ?? null) !== $slug));
        $this->settings->save('fonts', ['items' => $items]);

        if (preg_match('/^[a-z0-9-]+$/', $slug)) {
            Storage::disk('public')->deleteDirectory("fonts/{$slug}");
        }
    }

    /**
     * Arkusz CSS z Google Fonts (z nagłówkiem nowoczesnej przeglądarki, żeby dostać woff2).
     *
     * @param  list<int>  $weights
     * @return array{0: string, 1: list<int>}
     */
    private function fetchSheet(string $family, array $weights): array
    {
        $name = str_replace(' ', '+', $family);
        $attempts = [
            ["https://fonts.googleapis.com/css2?family={$name}:wght@".implode(';', $weights).'&display=swap', $weights],
            ["https://fonts.googleapis.com/css2?family={$name}&display=swap", [400]],
        ];

        foreach ($attempts as [$url, $used]) {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'])
                ->timeout(15)->get($url);

            if ($response->successful()) {
                return [$response->body(), $used];
            }

            if ($response->serverError()) {
                break;
            }
        }

        throw new FontException(__('Nie udało się pobrać czcionki „:name” z Google Fonts. Sprawdź nazwę i dostęp serwera do internetu.', ['name' => $family]));
    }

    /** @return list<array{url: string, weight: string, range: string}> */
    private function parse(string $sheet): array
    {
        preg_match_all('/\/\*\s*([a-z0-9-]+)\s*\*\/\s*@font-face\s*\{([^}]*)\}/i', $sheet, $blocks, PREG_SET_ORDER);
        $faces = [];

        foreach ($blocks as [, $subset, $body]) {
            if (! in_array(strtolower($subset), self::SUBSETS, true)
                || ! preg_match('/font-style:\s*normal/i', $body)
                || ! preg_match('/font-weight:\s*(\d{3}(?:\s+\d{3})?)/', $body, $w)
                || ! preg_match('/url\((https:\/\/fonts\.gstatic\.com\/[A-Za-z0-9_\-\/.]+\.woff2)\)/', $body, $u)) {
                continue;
            }

            $range = preg_match('/unicode-range:\s*([^;]+);/', $body, $r) && preg_match('/^[U+0-9A-Fa-f,\s?-]+$/', $r[1]) ? trim($r[1]) : '';
            $faces[] = ['url' => $u[1], 'weight' => $w[1], 'range' => $range];
        }

        return $faces;
    }

    private function download(string $url): string
    {
        $response = Http::timeout(20)->get($url);

        if (! $response->successful() || strlen($response->body()) > self::MAX_FILE_BYTES || ! str_starts_with($response->body(), 'wOF2')) {
            throw new FontException(__('Nie udało się pobrać pliku czcionki.'));
        }

        return $response->body();
    }
}
