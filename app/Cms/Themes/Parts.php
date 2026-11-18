<?php

namespace App\Cms\Themes;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\LocalCss;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Settings\Settings;
use App\Cms\Shortcodes\Shortcodes;

/**
 * Części globalne (nagłówek, stopka, własne): treści systemowego typu "part" edytowane w edytorze wizualnym.
 * Motyw deklaruje sloty w theme.json; slot bez przypisanej części używa domyślnego markupu motywu.
 */
class Parts
{
    public const TYPE = 'part';

    public function __construct(private readonly Settings $settings, private readonly ThemeManager $themes, private readonly Shortcodes $shortcodes) {}

    /** Typ treści dla części; tworzony przy pierwszym użyciu, więc działa też w istniejących instalacjach. */
    public function type(): ContentType
    {
        return ContentType::firstOrCreate(['key' => self::TYPE], [
            'label' => 'Część',
            'label_plural' => 'Części',
            'icon' => 'layout-template',
            'hierarchical' => false,
            'supports' => ['title', 'builder'],
            'url_prefix' => null,
            'capability_type' => 'page',
            'is_system' => true,
        ]);
    }

    /** @return list<array{slot: string, label: string}> */
    public function slots(): array
    {
        return $this->themes->active()->parts();
    }

    public function assignedId(string $slot): ?int
    {
        $id = ((array) $this->settings->get('theme_parts', 'slots', []))[$slot] ?? null;

        return $id ? (int) $id : null;
    }

    public function assign(string $slot, ?int $contentId): void
    {
        $slots = (array) $this->settings->get('theme_parts', 'slots', []);

        if ($contentId === null) {
            unset($slots[$slot]);
        } else {
            $slots[$slot] = $contentId;
        }

        $this->settings->save('theme_parts', ['slots' => $slots]);
    }

    /** HTML części przypisanej do slotu (z shortcodami) albo null, gdy slot używa domyślnego markupu motywu. */
    public function html(string $slot): ?string
    {
        $id = $this->assignedId($slot);

        return $id ? $this->htmlOf($id) : null;
    }

    public function htmlOf(int $contentId, ?Content $current = null): ?string
    {
        $part = Content::published()->whereHas('type', fn ($q) => $q->where('key', self::TYPE))->find($contentId);

        if (! $part || trim((string) $part->html_cache) === '') {
            return null;
        }

        app(LocalCss::class)->add($part->css_cache);

        return $this->shortcodes->render($part->html_cache, $current ?? $part);
    }

    /** Gotowe projekty części aktywnego motywu (opcjonalnie tylko dla jednego slotu). @return list<array{slot: string, key: string, label: string, description: string}> */
    public function presets(?string $slot = null): array
    {
        return collect($this->themes->active()->partPresets())
            ->filter(fn ($p) => ($slot === null || $p['slot'] === $slot) && $this->themes->active()->partPresetHtml($p['slot'], $p['key']) !== null)
            ->map(fn ($p) => ['label' => __($p['label']), 'description' => __($p['description'])] + $p)
            ->values()->all();
    }

    public function presetHtml(string $slot, string $key): ?string
    {
        $known = collect($this->presets($slot))->contains(fn ($p) => $p['key'] === $key);

        return $known ? $this->themes->active()->partPresetHtml($slot, $key) : null;
    }

    /** Projekt części z blokami motywu zamienionymi na gotowy markup (podgląd w panelu). */
    public function presetPreview(string $slot, string $key): ?string
    {
        $html = $this->presetHtml($slot, $key);

        if ($html === null) {
            return null;
        }

        $blocks = app(BlockRegistry::class);

        return preg_replace_callback(
            '/<div\s+data-gjs-type="lm-block"\s+data-gjs-block="([a-z0-9-]+)"(?:\s+data-gjs-attrs=\'([^\']*)\')?\s*>\s*<\/div>/',
            fn ($m) => $blocks->render($m[1], $blocks->normalize($m[1], (array) json_decode($m[2] ?? '[]', true))),
            $html,
        );
    }

    /** Tworzy część z gotowego projektu i przypisuje ją do slotu; treść trafia do edytora przy pierwszym otwarciu. */
    public function createFromPreset(string $slot, string $key): ?Content
    {
        $preset = collect($this->presets($slot))->firstWhere('key', $key);

        return $preset ? $this->create($preset['label'], $slot) : null;
    }

    /** Czy część została zapisana w edytorze (ma treść), żeby w panelu odróżnić ją od pustej. */
    public function create(string $title, ?string $slot = null): Content
    {
        $part = Content::create([
            'content_type_id' => $this->type()->id,
            'title' => $title,
            'slug' => 'part-'.str()->random(8),
            'status' => Content::STATUS_PUBLISHED,
            'published_at' => now(),
            'fields' => [],
            'author_id' => auth()->id(),
        ]);

        if ($slot !== null) {
            $this->assign($slot, $part->id);
        }

        return $part;
    }
}
