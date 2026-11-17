<?php

namespace App\Cms\Themes;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Settings\Settings;
use App\Cms\Taxonomy\Taxonomy;

/**
 * Theme Builder: szablony pojedynczych treści, archiwów i stron terminów budowane w edytorze wizualnym.
 * Szablon to część systemowa (typ "part") przypisana do klucza: single:{typ}, archive:{typ} albo taxonomy:{taksonomia}.
 * Brak przypisania oznacza widok motywu (single-{typ}, archive, taxonomy).
 */
class Templates
{
    public function __construct(private readonly Settings $settings, private readonly Parts $parts, private readonly ThemeManager $themes) {}

    /** @return list<array{key: string, kind: string, label: string, group: string, content_id: ?int}> */
    public function slots(): array
    {
        $out = [];

        foreach (ContentType::where('key', '!=', Parts::TYPE)->orderBy('id')->get() as $type) {
            $out[] = ['key' => "single:{$type->key}", 'kind' => 'single', 'label' => $type->label, 'group' => 'Pojedyncze treści'];

            if ($type->url_prefix && ! $type->hierarchical) {
                $out[] = ['key' => "archive:{$type->key}", 'kind' => 'archive', 'label' => $type->label_plural, 'group' => 'Archiwa'];
            }
        }

        foreach (Taxonomy::orderBy('id')->get() as $taxonomy) {
            $out[] = ['key' => "taxonomy:{$taxonomy->key}", 'kind' => 'taxonomy', 'label' => $taxonomy->label_plural, 'group' => 'Kategorie i tagi'];
        }

        return array_map(fn ($s) => $s + ['content_id' => $this->assignedId($s['key'])], $out);
    }

    public function known(string $key): bool
    {
        return collect($this->slots())->contains(fn ($s) => $s['key'] === $key);
    }

    public function assignedId(string $key): ?int
    {
        $id = ((array) $this->settings->get('theme_templates', 'map', []))[$key] ?? null;

        return $id ? (int) $id : null;
    }

    public function assign(string $key, ?int $contentId): void
    {
        $map = (array) $this->settings->get('theme_templates', 'map', []);

        if ($contentId === null) {
            unset($map[$key]);
        } else {
            $map[$key] = $contentId;
        }

        $this->settings->save('theme_templates', ['map' => $map]);
    }

    /** Odpina część od wszystkich szablonów (przy jej usunięciu). */
    public function unassignPart(int $contentId): void
    {
        foreach ((array) $this->settings->get('theme_templates', 'map', []) as $key => $id) {
            if ((int) $id === $contentId) {
                $this->assign($key, null);
            }
        }
    }

    /** Gotowy HTML szablonu (z shortcodami) dla bieżącej treści albo null, gdy użyć widoku motywu. */
    public function html(string $key, ?Content $current = null): ?string
    {
        $id = $this->assignedId($key);

        return $id ? $this->parts->htmlOf($id, $current) : null;
    }

    public function create(string $key): ?Content
    {
        $slot = collect($this->slots())->firstWhere('key', $key);

        if (! $slot) {
            return null;
        }

        $part = $this->parts->create(__($slot['group']).': '.$slot['label']);
        $this->assign($key, $part->id);

        return $part;
    }

    /** Projekt startowy szablonu z motywu: parts/template-{single|archive}.html. */
    public function starterHtml(string $kind): ?string
    {
        return $this->themes->active()->partPresetHtml('template', $kind === 'taxonomy' ? 'archive' : $kind);
    }
}
