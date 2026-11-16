<?php

namespace App\Cms\Updates;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Sprawdza w GitHub Releases, czy jest nowsza wersja systemu. Wynik trafia do cache, więc panel nie czeka na sieć. */
class UpdateChecker
{
    private const KEY = 'cms.update.latest';

    private const TTL_OK = 6 * 3600;

    private const TTL_ERROR = 1800;

    public function __construct(private readonly Cache $cache) {}

    public function enabled(): bool
    {
        return (bool) config('cms.update_checks');
    }

    public function current(): string
    {
        return (string) config('cms.version');
    }

    /** Ostatni znany wynik, bez zapytania do sieci. @return array{checked_at: int, release: array|null, error: string|null}|null */
    public function state(): ?array
    {
        $state = $this->cache->get(self::KEY);

        return is_array($state) ? $state : null;
    }

    /** Najnowsze wydanie z cache (null, gdy nie sprawdzono albo nie ma nowszej wersji niż obecna nie jest warunkiem). */
    public function cached(): ?Release
    {
        $release = $this->state()['release'] ?? null;

        return is_array($release) ? Release::fromArray($release) : null;
    }

    /** Czy jest nowsza wersja niż obecna (na podstawie cache). */
    public function updateAvailable(): bool
    {
        $release = $this->cached();

        return $release !== null && self::newer($release->version, $this->current());
    }

    /** Odpytuje GitHuba (albo oddaje cache) i zwraca najnowsze wydanie. */
    public function latest(bool $force = false): ?Release
    {
        if (! $this->enabled()) {
            return null;
        }

        if (! $force && $this->state() !== null) {
            return $this->cached();
        }

        return $this->refresh();
    }

    public function refresh(): ?Release
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $release = $this->fetch();
            $this->cache->put(self::KEY, ['checked_at' => time(), 'release' => $release?->toArray(), 'error' => null], self::TTL_OK);

            return $release;
        } catch (Throwable $e) {
            // Błąd sieci nie kasuje ostatniej znanej wersji, tylko zapamiętuje przyczynę i odkłada kolejną próbę.
            $previous = $this->state();
            $this->cache->put(self::KEY, [
                'checked_at' => time(), 'release' => $previous['release'] ?? null, 'error' => $e->getMessage(),
            ], self::TTL_ERROR);

            return $this->cached();
        }
    }

    public function forget(): void
    {
        $this->cache->forget(self::KEY);
    }

    /** Porównanie wersji semver ("v" z tagu jest ignorowane). */
    public static function newer(string $candidate, string $current): bool
    {
        return version_compare(ltrim($candidate, 'vV'), ltrim($current, 'vV'), '>');
    }

    private function fetch(): ?Release
    {
        $repo = (string) config('cms.update_repo');

        if (! preg_match('#^[\w.-]+/[\w.-]+$#', $repo)) {
            throw new UpdateException(__('Nieprawidłowe repozytorium wydań.'));
        }

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'LuminCMS/'.$this->current(),
        ])->timeout(8)->get("https://api.github.com/repos/{$repo}/releases/latest");

        if ($response->status() === 404) {
            return null; // Repozytorium nie ma jeszcze żadnego wydania.
        }

        if (! $response->successful()) {
            throw new UpdateException(__('GitHub odpowiedział kodem :code.', ['code' => $response->status()]));
        }

        $data = $response->json();
        $version = ltrim((string) ($data['tag_name'] ?? ''), 'vV');

        if (! is_array($data) || ! preg_match('/^\d+\.\d+\.\d+(?:-[\w.]+)?$/', $version)) {
            throw new UpdateException(__('Nie udało się odczytać numeru wersji wydania.'));
        }

        $prefix = "https://github.com/{$repo}/releases/download/";
        $assets = collect($data['assets'] ?? [])->filter(fn ($a) => is_array($a) && str_starts_with((string) ($a['browser_download_url'] ?? ''), $prefix));
        $zip = $assets->first(fn ($a) => preg_match('/^lumin-cms-[\w.+-]+\.zip$/', (string) ($a['name'] ?? '')));
        $checksum = $zip ? $assets->first(fn ($a) => ($a['name'] ?? '') === $zip['name'].'.sha256') : null;
        $digest = is_string($zip['digest'] ?? null) && preg_match('/^sha256:([a-f0-9]{64})$/i', $zip['digest'], $m) ? strtolower($m[1]) : null;

        return new Release(
            version: $version,
            name: (string) ($data['name'] ?: "Lumin CMS {$version}"),
            notes: (string) ($data['body'] ?? ''),
            url: (string) ($data['html_url'] ?? "https://github.com/{$repo}/releases"),
            publishedAt: $data['published_at'] ?? null,
            assetName: $zip['name'] ?? null,
            assetUrl: $zip['browser_download_url'] ?? null,
            assetSize: (int) ($zip['size'] ?? 0),
            sha256: $digest,
            checksumUrl: $checksum['browser_download_url'] ?? null,
        );
    }
}
