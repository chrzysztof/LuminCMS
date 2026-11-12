<?php

namespace App\Cms\Shortcodes;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Content\Content;
use App\Cms\Content\ContentResolver;
use App\Cms\Content\ContentType;
use App\Cms\Menus\MenuRenderer;
use App\Cms\Settings\Settings;
use App\Cms\Taxonomy\Term;

/** Shortcody dostępne od razu. */
class BuiltInShortcodes
{
    public static function register(Shortcodes $sc): void
    {
        $sc->add('year', fn () => e(date('Y')), '[year]', 'Bieżący rok, np. do stopki.');

        $sc->add('date', function (array $a) {
            $format = (string) ($a['format'] ?? 'd.m.Y');

            return e(now()->format(mb_substr($format, 0, 30)));
        }, '[date format="d.m.Y"]', 'Dzisiejsza data w podanym formacie (format PHP).');

        $sc->add('site_name', fn () => e(app(Settings::class)->get('seo', 'site_name', config('app.name'))), '[site_name]', 'Nazwa witryny z ustawień SEO.');

        $sc->add('site_url', fn () => e(rtrim(url('/'), '/')), '[site_url]', 'Adres główny witryny.');

        $sc->add('button', function (array $a, string $content) {
            $url = self::safeUrl($a['url'] ?? ($a[0] ?? ''));

            if ($url === null) {
                return '';
            }

            $class = ($a['style'] ?? '') === 'secondary' ? 'lm-btn lm-btn-secondary' : 'lm-btn';
            $new = isset($a['new']) && $a['new'] !== '0' && $a['new'] !== 'false';
            $label = trim($content) !== '' ? strip_tags($content, '<strong><em><b><i>') : e($a['label'] ?? $url);

            return '<a class="'.$class.'" href="'.e($url).'"'.($new ? ' target="_blank" rel="noopener noreferrer"' : '').'>'.$label.'</a>';
        }, '[button url="/kontakt" style="secondary"]Napisz do nas[/button]', 'Przycisk z odnośnikiem. Opcje: style="secondary", new="1" (nowa karta).');

        // Wartość pola własnego bieżącej treści (albo treści o podanym id).
        $sc->add('field', function (array $a, string $content, ?Content $current) {
            $source = isset($a['id']) ? Content::published()->find((int) $a['id']) : $current;
            $name = (string) ($a['name'] ?? ($a[0] ?? ''));

            if (! $source || $name === '') {
                return '';
            }

            $value = $source->field($name);

            return is_scalar($value) || $value instanceof \Stringable ? e((string) $value) : '';
        }, '[field name="telefon"] / [field name="telefon" id="12"]', 'Wartość pola własnego tej treści albo treści o podanym id.');

        // Odnośnik do treści po id.
        $sc->add('link', function (array $a, string $content) {
            $target = Content::published()->with('type', 'parent')->find((int) ($a['id'] ?? 0));

            if (! $target) {
                return e(strip_tags($content));
            }

            $label = trim($content) !== '' ? strip_tags($content, '<strong><em><b><i>') : e($target->title);

            return '<a href="'.e(url(app(ContentResolver::class)->pathFor($target))).'">'.$label.'</a>';
        }, '[link id="12"]Zobacz ofertę[/link]', 'Odnośnik do treści o podanym id; bez tekstu użyje jej tytułu.');

        // Blok motywu wstawiony w edytorze wizualnym (markup z szablonu bloku aktywnego motywu).
        $sc->add('block', function (array $a) {
            return app(BlockRegistry::class)->render(
                (string) ($a['name'] ?? ''),
                BlockRegistry::decode((string) ($a['data'] ?? '')),
            );
        }, '[block name="hero-banner" data="…"]', 'Blok motywu dodany w edytorze wizualnym. Wstawia go edytor, nie wpisuj tego kodu ręcznie.');

        // Menu nawigacji po slugu albo lokalizacji motywu.
        $sc->add('menu', function (array $a) {
            $renderer = app(MenuRenderer::class);

            return ! empty($a['location'])
                ? $renderer->forLocation((string) $a['location'])
                : $renderer->forSlug((string) ($a['name'] ?? ($a[0] ?? '')));
        }, '[menu name="glowne"] / [menu location="primary"]', 'Menu nawigacji (wielopoziomowe, z megamenu) wskazane nazwą (slugiem) albo lokalizacją motywu.');

        // Lista najnowszych treści, opcjonalnie z jednej taksonomii.
        $sc->add('posts', function (array $a) {
            $type = ContentType::where('key', $a['type'] ?? 'post')->first();

            if (! $type) {
                return '';
            }

            $limit = max(1, min(50, (int) ($a['limit'] ?? 5)));
            $query = Content::published()->with('type', 'parent')->where('content_type_id', $type->id);

            if (! empty($a['term'])) {
                $query->whereHas('terms', fn ($q) => $q
                    ->where('terms.slug', $a['term'])
                    ->when(! empty($a['taxonomy']), fn ($t) => $t->whereHas('taxonomy', fn ($x) => $x->where('key', $a['taxonomy']))));
            }

            $order = ($a['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $query = ($a['orderby'] ?? 'date') === 'title'
                ? $query->orderBy('title', $order)
                : $query->orderBy('published_at', $order)->orderBy('id', $order);

            $items = $query->limit($limit)->get();

            if ($items->isEmpty()) {
                return '';
            }

            $resolver = app(ContentResolver::class);
            $html = '<ul class="lm-posts">';

            foreach ($items as $item) {
                $html .= '<li><a href="'.e(url($resolver->pathFor($item))).'">'.e($item->title).'</a></li>';
            }

            return $html.'</ul>';
        }, '[posts type="post" limit="5" taxonomy="category" term="nowosci" orderby="date|title" order="desc|asc"]', 'Lista najnowszych opublikowanych treści, opcjonalnie z jednej kategorii lub tagu.');

        // Lista terminów taksonomii.
        $sc->add('terms', function (array $a) {
            $terms = Term::whereHas('taxonomy', fn ($q) => $q->where('key', $a['taxonomy'] ?? 'category'))->orderBy('name')->get(['name', 'slug']);

            if ($terms->isEmpty()) {
                return '';
            }

            return '<ul class="lm-terms">'.$terms->map(fn ($t) => '<li>'.e($t->name).'</li>')->implode('').'</ul>';
        }, '[terms taxonomy="category"]', 'Lista terminów wybranej taksonomii.');
    }

    /** Tylko adresy względne, http(s), mailto i tel; resztę (np. javascript:) odrzucamy. */
    private static function safeUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || preg_match('~[\x00-\x1f\s]~', $url)) {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') || str_starts_with($url, '#')) {
            return $url;
        }

        return preg_match('~^(https?://|mailto:|tel:)~i', $url) ? $url : null;
    }
}
