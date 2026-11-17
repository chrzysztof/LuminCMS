<?php

namespace App\Cms\Shortcodes;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Content\Content;
use App\Cms\Content\ContentResolver;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldOutput;
use App\Cms\Menus\MenuRenderer;
use App\Cms\Settings\Settings;
use App\Cms\Settings\SiteIdentity;
use App\Cms\Taxonomy\Term;
use Illuminate\Support\Carbon;

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

        $sc->add('site_logo', fn () => app(SiteIdentity::class)->logoHtml() ?? e(app(Settings::class)->get('seo', 'site_name', config('app.name'))), '[site_logo]', 'Logo witryny (a gdy go nie ustawiono, jej nazwa).');

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

        // Wartość pola własnego bieżącej treści (albo treści o podanym id). Zawartość między znacznikami
        // jest wartością zastępczą, gdy pole jest puste: [field name="podtytul"]Domyślny podtytuł[/field].
        $sc->add('field', function (array $a, string $content, ?Content $current) {
            $source = isset($a['id']) ? Content::published()->find((int) $a['id']) : $current;
            $name = (string) ($a['name'] ?? ($a[0] ?? ''));
            $format = in_array($a['format'] ?? 'text', FieldOutput::FORMATS, true) ? ($a['format'] ?? 'text') : 'text';
            $fallback = $format === 'url' ? (FieldOutput::url($a['default'] ?? '') ?? '') : $content;

            $value = $source && $name !== '' ? FieldOutput::builtIn($source, $name) ?? $source->field($name) : null;
            $out = match ($format) {
                'html' => FieldOutput::html($value),
                'url' => FieldOutput::url($value) === null ? '' : e(FieldOutput::url($value)),
                'image' => FieldOutput::image($value, preg_match('/^[a-z0-9_]+$/', $a['size'] ?? '') ? $a['size'] : 'large', preg_replace('/[^A-Za-z0-9_ -]/', '', $a['class'] ?? ''), (string) ($a['alt'] ?? '')),
                default => FieldOutput::text($value),
            };

            return $out !== '' ? $out : ($format === 'url' ? e($fallback) : $fallback);
        }, '[field name="telefon"] / [field name="zdjecie" format="image"] / [field name="www" format="url"]', 'Wartość pola własnego tej treści albo treści o podanym id. Formaty: text, html, url, image; zawartość między znacznikami to wartość zastępcza.');

        // Warunek wyświetlania: [show when="filled" field="telefon"]...[/show]. Warunki: logged_in, logged_out,
        // filled / empty (pole własne albo wbudowane _featured, _title, _excerpt), period (from/to jako RRRR-MM-DD).
        $sc->add('show', function (array $a, string $content, ?Content $current) {
            $field = fn () => $current && ($name = (string) ($a['field'] ?? '')) !== '' ? FieldOutput::builtIn($current, $name) ?? $current->field($name) : null;

            $visible = match ($a['when'] ?? '') {
                'logged_in' => auth()->check(),
                'logged_out' => ! auth()->check(),
                'filled' => FieldOutput::filled($field()),
                'empty' => ! FieldOutput::filled($field()),
                'period' => self::inPeriod($a['from'] ?? '', $a['to'] ?? ''),
                default => false, // nieznany warunek nie ujawnia treści
            };

            return $visible ? $content : '';
        }, '[show when="filled" field="telefon"]Zadzwoń[/show]', 'Pokazuje zawartość tylko po spełnieniu warunku: logged_in, logged_out, filled, empty (pole), period (from, to).');
        // Zagnieżdżone warunki (edytor wizualny) używają nazw show-1 ... show-5.
        for ($i = 1; $i <= 5; $i++) {
            $sc->alias("show-{$i}", 'show');
        }

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
        $sc->add('block', function (array $a, string $content, ?Content $current) {
            return app(BlockRegistry::class)->render(
                (string) ($a['name'] ?? ''),
                BlockRegistry::decode((string) ($a['data'] ?? '')),
                $current,
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

    private static function inPeriod(string $from, string $to): bool
    {
        $now = now();

        try {
            if ($from !== '' && $now->lt(Carbon::parse($from)->startOfDay())) {
                return false;
            }
            if ($to !== '' && $now->gt(Carbon::parse($to)->endOfDay())) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return $from !== '' || $to !== '';
    }
}
