<?php

namespace App\Cms\Seo;

use App\Cms\Content\Content;
use App\Cms\Content\ContentResolver;
use App\Cms\Media\Media;
use App\Cms\Settings\Settings;
use Illuminate\Support\Str;

/**
 * Składa dane SEO treści: tytuł, opis, canonical, robots, Open Graph, Twitter i JSON-LD.
 * Kolejność źródeł: ustawienia treści → zajawka / początek treści → ustawienia globalne.
 */
class SeoResolver
{
    public const DESCRIPTION_LIMIT = 160;

    public function __construct(
        private readonly Settings $settings,
        private readonly ContentResolver $paths,
    ) {}

    public function site(): array
    {
        return $this->settings->group('seo');
    }

    public function isHome(Content $content): bool
    {
        return $content->slug === 'home' && $content->parent_id === null && $content->type?->key === 'page';
    }

    public function urlFor(Content $content): string
    {
        return $this->isHome($content) ? url('/') : url($this->paths->pathFor($content));
    }

    /** Tytuł strony wg szablonu, np. "O nas – Moja firma". */
    public function title(Content $content): string
    {
        $site = $this->site();
        $custom = trim((string) ($content->seo['title'] ?? ''));

        if ($custom === '' && $this->isHome($content)) {
            return $this->join([$site['site_name'], $site['tagline']], $site['separator']);
        }

        return $this->applyTemplate($site['title_template'], $custom !== '' ? $custom : $content->title, $site);
    }

    public function applyTemplate(string $template, string $title, array $site): string
    {
        $out = strtr($template, [
            '%title%' => $title,
            '%site%' => $site['site_name'],
            '%tagline%' => $site['tagline'],
            '%sep%' => $site['separator'],
        ]);

        return trim(preg_replace('/\s+/', ' ', $out) ?? $out);
    }

    public function description(Content $content): string
    {
        $custom = trim((string) ($content->seo['description'] ?? ''));

        if ($custom !== '') {
            return $custom;
        }

        $source = trim((string) $content->excerpt) !== '' ? (string) $content->excerpt : (string) $content->html_cache;
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($source), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return $text !== '' ? Str::limit($text, self::DESCRIPTION_LIMIT, '…', preserveWords: true) : (string) $this->site()['description'];
    }

    public function robots(Content $content, bool $preview = false): string
    {
        if ($preview || $this->site()['noindex_site'] || ! empty($content->seo['noindex'])) {
            return 'noindex, nofollow';
        }

        $follow = ! empty($content->seo['nofollow']) ? 'nofollow' : 'follow';

        return "index, {$follow}, max-image-preview:large";
    }

    public function indexable(Content $content): bool
    {
        return ! $this->site()['noindex_site'] && empty($content->seo['noindex']) && in_array('seo', $content->type?->supports ?? [], true);
    }

    /** Obraz do udostępniania: z treści, potem obraz wyróżniający, na końcu domyślny z ustawień. */
    public function image(Content $content): ?array
    {
        foreach ([$content->seo['og_image_id'] ?? null, $content->featured_media_id, $this->site()['default_og_image_id']] as $id) {
            $media = $id ? Media::find($id) : null;

            if ($media && $media->isImage()) {
                [$w, $h] = $media->dimensions('large');

                return ['url' => $this->absolute($media->url('large')), 'width' => $w ?: null, 'height' => $h ?: null, 'alt' => $media->alt ?: null];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function forContent(Content $content, bool $preview = false): array
    {
        $content->loadMissing('type', 'parent', 'author');
        $site = $this->site();
        $title = $this->title($content);
        $description = $this->description($content);
        $canonical = ($content->seo['canonical'] ?? '') !== '' ? (string) $content->seo['canonical'] : $this->urlFor($content);
        $image = $this->image($content);
        $ogTitle = trim((string) ($content->seo['og_title'] ?? '')) ?: $title;
        $ogDescription = trim((string) ($content->seo['og_description'] ?? '')) ?: $description;

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $preview ? null : $canonical,
            'robots' => $this->robots($content, $preview),
            'og' => array_filter([
                'og:type' => $content->type?->key === 'post' ? 'article' : 'website',
                'og:title' => $ogTitle,
                'og:description' => $ogDescription,
                'og:url' => $canonical,
                'og:site_name' => $site['site_name'],
                'og:locale' => str_replace('-', '_', app()->getLocale()),
                'og:image' => $image['url'] ?? null,
                'og:image:width' => $image['width'] ?? null,
                'og:image:height' => $image['height'] ?? null,
                'og:image:alt' => $image['alt'] ?? null,
            ]),
            'twitter' => array_filter([
                'twitter:card' => $image ? 'summary_large_image' : 'summary',
                'twitter:title' => $ogTitle,
                'twitter:description' => $ogDescription,
                'twitter:image' => $image['url'] ?? null,
                'twitter:site' => $site['twitter_handle'] !== '' ? '@'.ltrim((string) $site['twitter_handle'], '@') : null,
            ]),
            'jsonld' => $this->jsonLd($content, $title, $description, $canonical, $image),
        ];
    }

    private function jsonLd(Content $content, string $title, string $description, string $canonical, ?array $image): array
    {
        $site = $this->site();
        $graph = [[
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'url' => url('/'),
            'name' => $site['site_name'],
        ]];

        if ($this->isHome($content)) {
            $graph[0]['description'] = $site['tagline'] ?: $site['description'];
        }

        $page = [
            '@type' => $content->type?->key === 'post' ? 'Article' : 'WebPage',
            '@id' => $canonical.'#page',
            'url' => $canonical,
            $content->type?->key === 'post' ? 'headline' : 'name' => $content->seo['title'] ?? $content->title,
            'description' => $description,
            'isPartOf' => ['@id' => url('/').'#website'],
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
        ];

        if ($content->published_at) {
            $page['datePublished'] = $content->published_at->toIso8601String();
        }
        if ($content->updated_at) {
            $page['dateModified'] = $content->updated_at->toIso8601String();
        }
        if ($image) {
            $page['image'] = $image['url'];
        }
        if ($page['@type'] === 'Article' && $content->author) {
            $page['author'] = ['@type' => 'Person', 'name' => $content->author->name];
        }

        $graph[] = $page;

        // Okruszki: strona główna → rodzice → bieżąca (tylko gdy jest co pokazać).
        $trail = [];
        for ($p = $content->parent, $i = 0; $p && $i < 20; $p = $p->parent, $i++) {
            array_unshift($trail, $p);
        }

        if ($trail !== [] || ! $this->isHome($content)) {
            $items = [['name' => $site['site_name'], 'url' => url('/')]];
            foreach ($trail as $p) {
                $items[] = ['name' => $p->title, 'url' => $this->urlFor($p)];
            }
            $items[] = ['name' => $content->title, 'url' => $canonical];

            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn ($item, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'item' => $item['url']], $items, array_keys($items)),
            ];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    private function join(array $parts, string $separator): string
    {
        return implode(" {$separator} ", array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    private function absolute(string $url): string
    {
        return preg_match('~^https?://~i', $url) ? $url : url($url);
    }
}
