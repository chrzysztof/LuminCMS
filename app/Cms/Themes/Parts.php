<?php

namespace App\Cms\Themes;

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

    public function htmlOf(int $contentId): ?string
    {
        $part = Content::published()->whereHas('type', fn ($q) => $q->where('key', self::TYPE))->find($contentId);

        if (! $part || trim((string) $part->html_cache) === '') {
            return null;
        }

        return $this->shortcodes->render($part->html_cache, $part);
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
