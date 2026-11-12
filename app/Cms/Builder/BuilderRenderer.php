<?php

namespace App\Cms\Builder;

use App\Cms\Media\Media;
use Illuminate\Support\Collection;

/**
 * Zamienia dane projektu GrapesJS (JSON) na czysty, semantyczny HTML.
 *
 * Zasady:
 *  - tylko tagi z białej listy (bez script, iframe, style, formularzy),
 *  - tylko bezpieczne atrybuty (bez style i on*), adresy bez javascript:,
 *  - style wyłącznie przez klasy CSS (tokeny z lumin-blocks.css), bez inline CSS z edytora,
 *  - obrazy z biblioteki mediów jako <picture> (WebP, srcset, width/height, lazy loading).
 */
class BuilderRenderer
{
    private const TAGS = [
        'section', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'div', 'span',
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'a', 'img', 'figure', 'figcaption',
        'blockquote', 'strong', 'em', 'b', 'i', 'u', 's', 'small', 'mark', 'br', 'hr', 'pre', 'code',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'details', 'summary',
    ];

    /** GrapesJS pomija tagName w JSON, gdy jest domyślny dla typu komponentu. */
    private const TYPE_TAGS = [
        'link' => 'a', 'lm-button' => 'a', 'image' => 'img', 'lm-image' => 'img',
        'lm-section' => 'section', 'lm-paragraph' => 'p', 'lm-heading' => 'h2',
    ];

    private const VOID = ['img', 'br', 'hr'];

    // Bez "id": GrapesJS generuje losowe identyfikatory (np. "i74n"), które zaśmiecałyby HTML.
    private const ATTRIBUTES = ['title', 'role', 'lang', 'href', 'target', 'rel', 'src', 'alt', 'width', 'height', 'colspan', 'rowspan', 'scope'];

    private const MAX_DEPTH = 40;

    /** @var Collection<int, Media> */
    private Collection $media;

    /** @param  array<string, mixed>  $project  wynik editor.getProjectData() */
    public function render(array $project): string
    {
        $root = $project['pages'][0]['frames'][0]['component'] ?? null;

        if (! is_array($root)) {
            return '';
        }

        $children = $root['components'] ?? [];
        $this->media = Media::whereIn('id', $this->mediaIds($children))->get()->keyBy('id');

        return trim($this->renderChildren($children, 0));
    }

    /** @return list<int> */
    private function mediaIds(mixed $components, int $depth = 0): array
    {
        $ids = [];

        if (! is_array($components) || $depth > self::MAX_DEPTH) {
            return $ids;
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $id = $component['attributes']['data-media-id'] ?? null;

            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }

            $ids = [...$ids, ...$this->mediaIds($component['components'] ?? [], $depth + 1)];
        }

        return $ids;
    }

    private function renderChildren(mixed $components, int $depth): string
    {
        if (! is_array($components) || $depth > self::MAX_DEPTH) {
            return '';
        }

        $html = '';

        foreach ($components as $component) {
            if (is_array($component)) {
                $html .= $this->renderComponent($component, $depth);
            }
        }

        return $html;
    }

    private function renderComponent(array $component, int $depth): string
    {
        $type = $component['type'] ?? '';

        if ($type === 'textnode') {
            return e((string) ($component['content'] ?? ''));
        }

        if ($type === 'comment') {
            return '';
        }

        $tag = strtolower((string) ($component['tagName'] ?? self::TYPE_TAGS[$type] ?? 'div'));

        if (! in_array($tag, self::TAGS, true)) {
            // Nieznany tag: zachowaj treść, pomiń opakowanie.
            return $this->inner($component, $depth);
        }

        $attributes = (array) ($component['attributes'] ?? []);
        $classes = $this->classes($component['classes'] ?? [], $attributes['class'] ?? '');

        if ($tag === 'img' && is_numeric($attributes['data-media-id'] ?? null)) {
            return $this->picture($attributes, $classes);
        }

        // Obraz bez pliku z biblioteki i bez bezpiecznego adresu nie ma czego pokazać.
        if ($tag === 'img' && ! (is_string($attributes['src'] ?? null) && $attributes['src'] !== '' && $this->safeUrl($attributes['src']))) {
            return '';
        }

        $open = '<'.$tag.$this->attributeString($tag, $attributes, $classes);

        if (in_array($tag, self::VOID, true)) {
            return $open.'>';
        }

        return $open.'>'.$this->inner($component, $depth).'</'.$tag.'>';
    }

    private function inner(array $component, int $depth): string
    {
        $content = $component['content'] ?? null;

        if (is_string($content) && $content !== '' && empty($component['components'])) {
            return e($content);
        }

        return $this->renderChildren($component['components'] ?? [], $depth + 1);
    }

    private function picture(array $attributes, array $classes): string
    {
        $media = $this->media->get((int) $attributes['data-media-id']);

        if (! $media) {
            return '';
        }

        $size = preg_match('/^[a-z0-9_]+$/', (string) ($attributes['data-size'] ?? '')) ? $attributes['data-size'] : 'large';
        $img = array_filter([
            'class' => $classes ? implode(' ', $classes) : null,
            'alt' => ($attributes['alt'] ?? '') !== '' ? (string) $attributes['alt'] : null,
        ], fn ($v) => $v !== null);

        return (string) $media->picture($size, (string) ($attributes['data-sizes'] ?? '100vw'), $img, ($attributes['data-eager'] ?? null) === 'true');
    }

    /** @param  list<string|array<string, mixed>>  $classes */
    private function classes(mixed $classes, mixed $attributeClass): array
    {
        $names = [];

        foreach ((array) $classes as $class) {
            if (is_array($class)) {
                if (($class['active'] ?? true) === false) {
                    continue;
                }
                $class = $class['name'] ?? '';
            }

            $names[] = (string) $class;
        }

        if (is_string($attributeClass) && $attributeClass !== '') {
            $names = [...$names, ...preg_split('/\s+/', trim($attributeClass))];
        }

        return array_values(array_unique(array_filter($names, fn ($n) => preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,60}$/', $n) === 1)));
    }

    private function attributeString(string $tag, array $attributes, array $classes): string
    {
        $out = $classes ? ' class="'.e(implode(' ', $classes)).'"' : '';

        foreach (self::ATTRIBUTES as $name) {
            $value = $attributes[$name] ?? null;

            if (! is_scalar($value) || $value === '' || $value === false) {
                continue;
            }

            $value = (string) $value;

            if (in_array($name, ['href', 'src'], true)) {
                if (! $this->safeUrl($value) || ($name === 'src' && $tag !== 'img')) {
                    continue;
                }
            }

            if ($name === 'target' && ! in_array($value, ['_blank', '_self'], true)) {
                continue;
            }

            if ($name === 'rel' && $tag !== 'a') {
                continue;
            }

            $out .= ' '.$name.'="'.e($value).'"';
        }

        foreach ($attributes as $name => $value) {
            if (is_string($name) && preg_match('/^aria-[a-z-]+$/', $name) && is_scalar($value)) {
                $out .= ' '.$name.'="'.e((string) $value).'"';
            }
        }

        if ($tag === 'a' && ($attributes['target'] ?? null) === '_blank') {
            $out = preg_replace('/ rel="[^"]*"/', '', $out).' rel="noopener noreferrer"';
        }

        if ($tag === 'img') {
            $out .= ' loading="lazy" decoding="async"';
            if (($attributes['alt'] ?? '') === '') {
                $out .= ' alt=""';
            }
        }

        return $out;
    }

    private function safeUrl(string $url): bool
    {
        $clean = preg_replace('/[\x00-\x20\x7F]+/', '', $url);

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $clean)) {
            return (bool) preg_match('/^(https?|mailto|tel):/i', $clean);
        }

        return true;
    }
}
