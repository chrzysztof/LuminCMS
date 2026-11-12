<?php

namespace App\Cms\Menus;

use App\Cms\Content\ArchiveResolver;
use App\Cms\Content\ContentResolver;
use App\Cms\Themes\Parts;

/**
 * Renderuje menu jako dostępny HTML: <nav> z listą zagnieżdżoną, przyciskami rozwijania (aria-expanded)
 * i megamenu (kolumny z pozycji potomnych plus opcjonalny panel z części globalnej).
 * Zachowanie (otwieranie, Escape, mobilne) daje assets/menu.js motywu, wygląd menu.css.
 */
class MenuRenderer
{
    private int $counter = 0;

    public function __construct(
        private readonly ContentResolver $contents,
        private readonly ArchiveResolver $archives,
        private readonly Parts $parts,
    ) {}

    public function forLocation(string $location): string
    {
        $menu = Menu::where('location', $location)->first();

        return $menu ? $this->render($menu) : '';
    }

    public function forSlug(string $slug): string
    {
        $menu = Menu::where('slug', $slug)->first();

        return $menu ? $this->render($menu) : '';
    }

    public function render(Menu $menu, ?string $currentPath = null): string
    {
        $tree = $this->tree($menu);

        if ($tree === []) {
            return '';
        }

        $current = trim($currentPath ?? request()->path(), '/');
        $id = 'lm-menu-'.$menu->id.'-'.(++$this->counter);

        return '<nav class="lm-menu" aria-label="'.e($menu->name).'" data-lm-menu>'
            .'<button type="button" class="lm-menu__burger" aria-expanded="false" aria-controls="'.e($id).'" aria-label="'.e(__('Menu')).'"><span aria-hidden="true"></span></button>'
            .$this->list($tree, $current, 'lm-menu__list', $id)
            .'</nav>';
    }

    /**
     * Drzewo pozycji z rozwiązanymi adresami; pozycje wskazujące na nieopublikowaną lub usuniętą treść są pomijane.
     *
     * @return list<array<string, mixed>>
     */
    public function tree(Menu $menu): array
    {
        $items = $menu->items()->with(['content.type', 'content.parent', 'term.taxonomy'])->get();
        $byParent = $items->groupBy(fn (MenuItem $i) => $i->parent_id ?? 0);

        $build = function (int $parentId) use (&$build, $byParent): array {
            $nodes = [];

            foreach ($byParent->get($parentId, collect()) as $item) {
                $node = $this->resolve($item);

                if ($node === null) {
                    continue;
                }

                $node['children'] = $build($item->id);
                $nodes[] = $node;
            }

            return $nodes;
        };

        return $build(0);
    }

    /** @return array<string, mixed>|null */
    private function resolve(MenuItem $item): ?array
    {
        $label = (string) $item->label;
        $url = null;

        switch ($item->type) {
            case 'content':
                $content = $item->content;

                if (! $content || $content->status !== 'published' || $content->published_at?->isFuture()) {
                    return null;
                }

                $url = $this->contents->urlFor($content);
                $label = $label !== '' ? $label : $content->title;
                break;
            case 'term':
                if (! $item->term) {
                    return null;
                }

                $url = $this->archives->urlForTerm($item->term);
                $label = $label !== '' ? $label : $item->term->name;
                break;
            case 'custom':
                $url = $this->safeUrl((string) $item->url);
                break;
        }

        if ($label === '') {
            return null;
        }

        return [
            'label' => $label,
            'url' => $url,
            'new_tab' => $item->new_tab && $url !== null,
            'mega' => (bool) $item->mega,
            'mega_layout' => in_array($item->mega_layout, MenuItem::LAYOUTS, true) ? $item->mega_layout : 'container',
            'mega_part_id' => $item->mega_part_id,
        ];
    }

    /** Dopuszcza tylko adresy http(s), względne, kotwice, mailto i tel (bez javascript: itp.). */
    public function safeUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', $url) !== 1) {
            return null;
        }

        return $url;
    }

    /** @param  list<array<string, mixed>>  $nodes */
    private function list(array $nodes, string $current, string $class, ?string $id = null, bool $top = true): string
    {
        $html = '<ul class="'.$class.'"'.($id ? ' id="'.e($id).'"' : '').'>';

        foreach ($nodes as $node) {
            $html .= $this->item($node, $current, $top);
        }

        return $html.'</ul>';
    }

    /** @param  array<string, mixed>  $node */
    private function item(array $node, string $current, bool $top): string
    {
        $mega = $top && $node['mega'];
        $extra = $mega && $node['mega_part_id'] ? $this->parts->htmlOf((int) $node['mega_part_id']) : null;
        $hasPanel = $node['children'] !== [] || $extra !== null;
        $isCurrent = $this->isCurrent($node['url'], $current);
        $inTrail = $isCurrent || $this->containsCurrent($node['children'], $current);

        $classes = ['lm-menu__item'];
        $hasPanel && $classes[] = 'has-children';
        $mega && $classes[] = 'is-mega';
        $isCurrent && $classes[] = 'is-current';
        $inTrail && ! $isCurrent && $classes[] = 'is-trail';

        $html = '<li class="'.implode(' ', $classes).'">'.$this->link($node, $isCurrent, 'lm-menu__link');

        if ($hasPanel) {
            $html .= '<button type="button" class="lm-menu__toggle" aria-expanded="false" aria-label="'.e(__('Rozwiń podmenu').': '.$node['label']).'"><span aria-hidden="true"></span></button>';
            $html .= $mega ? $this->megaPanel($node, $current, $extra) : $this->list($node['children'], $current, 'lm-menu__sub', null, false);
        }

        return $html.'</li>';
    }

    /** @param  array<string, mixed>  $node */
    private function megaPanel(array $node, string $current, ?string $extra): string
    {
        $html = '<div class="lm-menu__mega lm-menu__mega--'.e($node['mega_layout']).'"><div class="lm-menu__cols">';

        foreach ($node['children'] as $column) {
            $title = $column['url'] !== null
                ? $this->link($column, $this->isCurrent($column['url'], $current), 'lm-menu__col-title')
                : '<span class="lm-menu__col-title">'.e($column['label']).'</span>';

            $html .= '<div class="lm-menu__col">'.$title
                .($column['children'] !== [] ? $this->list($column['children'], $current, 'lm-menu__sub', null, false) : '')
                .'</div>';
        }

        $html .= '</div>';

        if ($extra !== null) {
            $html .= '<div class="lm-menu__extra">'.$extra.'</div>';
        }

        return $html.'</div>';
    }

    /** @param  array<string, mixed>  $node */
    private function link(array $node, bool $current, string $class): string
    {
        if ($node['url'] === null) {
            return '<span class="'.$class.' lm-menu__text">'.e($node['label']).'</span>';
        }

        return '<a class="'.$class.'" href="'.e($node['url']).'"'
            .($current ? ' aria-current="page"' : '')
            .($node['new_tab'] ? ' target="_blank" rel="noopener"' : '')
            .'>'.e($node['label']).'</a>';
    }

    private function isCurrent(?string $url, string $current): bool
    {
        if ($url === null || str_starts_with($url, '#') || preg_match('#^(mailto|tel):#i', $url)) {
            return false;
        }

        $path = parse_url($url);

        if (isset($path['host']) && $path['host'] !== parse_url(url('/'), PHP_URL_HOST)) {
            return false;
        }

        return trim((string) ($path['path'] ?? ''), '/') === $current;
    }

    /** @param  list<array<string, mixed>>  $nodes */
    private function containsCurrent(array $nodes, string $current): bool
    {
        foreach ($nodes as $node) {
            if ($this->isCurrent($node['url'], $current) || $this->containsCurrent($node['children'], $current)) {
                return true;
            }
        }

        return false;
    }
}
