<?php

namespace App\Cms\Themes;

use App\Cms\Content\ContentType;
use App\Cms\Settings\Settings;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * Wyszukuje motywy w themes/, wskazuje aktywny (ustawienie theme.active) i udostępnia jego widoki
 * jako namespace Blade "theme::". Brakujące widoki aktywnego motywu bierzemy z motywu domyślnego.
 */
class ThemeManager
{
    public const DEFAULT = 'lumin-default';

    /** @var array<string, Theme>|null */
    private ?array $themes = null;

    public function __construct(private readonly Settings $settings, private readonly string $directory) {}

    /** @return array<string, Theme> */
    public function all(): array
    {
        if ($this->themes !== null) {
            return $this->themes;
        }

        $this->themes = [];

        foreach (glob($this->directory.'/*/theme.json') ?: [] as $file) {
            $slug = basename(dirname($file));
            $manifest = json_decode((string) file_get_contents($file), true);

            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) && is_array($manifest)) {
                $this->themes[$slug] = new Theme($slug, dirname($file), $manifest);
            }
        }

        $this->link();

        return $this->themes;
    }

    /** Ustawia motywy nadrzędne (z ochroną przed cyklami) i motyw domyślny jako zapas. */
    private function link(): void
    {
        $default = $this->themes[self::DEFAULT] ?? null;

        foreach ($this->themes as $theme) {
            $theme->fallback = $theme->slug === self::DEFAULT ? null : $default;
            $parentSlug = $theme->parentSlug();

            if ($parentSlug === null) {
                continue;
            }

            $seen = [$theme->slug];
            $parent = $this->themes[$parentSlug] ?? null;

            for ($p = $parent; $p; $p = $this->themes[$p->parentSlug() ?? ''] ?? null) {
                if (in_array($p->slug, $seen, true)) {
                    $theme->error = 'cycle';
                    $parent = null;
                    break;
                }
                $seen[] = $p->slug;
            }

            if ($parent === null && $theme->error === null) {
                $theme->error = 'missing_parent';
            }

            $theme->parent = $theme->error === null ? $parent : null;
        }
    }

    /** Wymusza ponowne przeskanowanie folderu motywów (po instalacji lub usunięciu). */
    public function refresh(): void
    {
        $this->themes = null;
    }

    public function has(string $slug): bool
    {
        return isset($this->all()[$slug]);
    }

    public function active(): Theme
    {
        $slug = (string) $this->settings->get('theme', 'active', self::DEFAULT);

        return $this->all()[$slug] ?? $this->all()[self::DEFAULT] ?? throw new \RuntimeException('Brak motywu domyślnego '.self::DEFAULT);
    }

    public function activate(string $slug): void
    {
        if (! $this->has($slug)) {
            // Motyw mógł zostać wgrany po starcie aplikacji: skanujemy folder jeszcze raz.
            $this->themes = null;
        }

        if (! $this->has($slug)) {
            throw new \InvalidArgumentException("Nie ma motywu: {$slug}");
        }

        if ($this->all()[$slug]->error !== null) {
            throw new \InvalidArgumentException("Motyw {$slug} nie może być aktywny: brakuje motywu nadrzędnego albo dziedziczenie jest cykliczne.");
        }

        $this->settings->save('theme', ['active' => $slug]);
        $this->register();
    }

    /** Podpina widoki aktywnego motywu (z fallbackiem na domyślny) pod namespace "theme". */
    public function register(): void
    {
        try {
            $active = $this->active();
        } catch (Throwable) {
            // Baza jeszcze niegotowa (np. przed migracją): bierzemy motyw domyślny.
            $active = $this->all()[self::DEFAULT] ?? null;
        }

        if (! $active) {
            return;
        }

        View::replaceNamespace('theme', $active->viewPaths());
    }

    /** @return list<array{key: string, label: string}> szablony aktywnego motywu dla danego typu treści */
    public function templatesFor(ContentType $type): array
    {
        return collect($this->active()->templates())
            ->filter(fn ($t) => $t['types'] === [] || in_array($type->key, $t['types'], true))
            ->map(fn ($t) => ['key' => $t['key'], 'label' => __($t['label'])])
            ->values()
            ->all();
    }

    /** Pierwszy istniejący widok z listy kandydatów (nazwy bez prefiksu "theme::"). */
    public function view(array $candidates, array $data = []): ViewContract
    {
        return View::first(array_map(fn ($c) => 'theme::'.$c, $candidates), $data);
    }
}
