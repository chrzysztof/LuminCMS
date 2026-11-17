<?php

namespace App\Cms\Builder;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Media\Media;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use Illuminate\Support\Collection;

/**
 * Dane bloku "Lista wpisów": opublikowane treści wybranego typu z filtrami ustawionymi w edytorze
 * (kategoria lub tag, kolejność, liczba) oraz filtrem wybieranym przez odwiedzającego (?filtr=slug).
 */
class PostsList
{
    public const QUERY = 'filtr';

    public const PAGE_QUERY = 'strona';

    private const ORDER = ['date', 'title', 'menu_order'];

    /**
     * @param  array<string, mixed>  $attrs  wartości pól bloku
     * @return array{items: Collection<int, Content>, media: Collection<int, Media>, filters: list<array{slug: string, name: string}>, active: ?string, type: ?ContentType, page: int, pages: int}
     */
    public function get(array $attrs, ?Content $current = null, ?string $visitorTerm = null, int $page = 1): array
    {
        $type = ContentType::where('key', (string) (($attrs['type'] ?? '') ?: 'post'))->where('key', '!=', 'part')->first();
        $empty = ['items' => collect(), 'media' => collect(), 'filters' => [], 'active' => null, 'type' => $type, 'page' => 1, 'pages' => 1];

        if (! $type) {
            return $empty;
        }

        $filterTaxonomy = Taxonomy::where('key', (string) ($attrs['filter_taxonomy'] ?? ''))->first();
        $filters = $filterTaxonomy ? $this->filters($type, $filterTaxonomy) : [];
        $active = $visitorTerm !== null && in_array($visitorTerm, array_column($filters, 'slug'), true) ? $visitorTerm : null;

        $query = Content::published()->with('type', 'parent')->where('content_type_id', $type->id);

        if ([$taxonomyKey, $slug] = $this->parseTerm((string) ($attrs['term'] ?? ''))) {
            $query->whereHas('terms', fn ($q) => $q->where('terms.slug', $slug)->whereHas('taxonomy', fn ($x) => $x->where('key', $taxonomyKey)));
        }

        if ($active !== null && $filterTaxonomy) {
            $query->whereHas('terms', fn ($q) => $q->where('terms.slug', $active)->where('terms.taxonomy_id', $filterTaxonomy->id));
        }

        if ($current && ! empty($attrs['exclude_current'])) {
            $query->whereKeyNot($current->getKey());
        }

        $order = ($attrs['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $by = in_array($attrs['orderby'] ?? 'date', self::ORDER, true) ? $attrs['orderby'] : 'date';

        match ($by) {
            'title' => $query->orderBy('title', $order),
            'menu_order' => $query->orderBy('menu_order', $order)->orderBy('title'),
            default => $query->orderBy('published_at', $order)->orderBy('id', $order),
        };

        $limit = max(1, min(24, (int) (($attrs['limit'] ?? 0) ?: 6)));
        $pages = 1;

        if (! empty($attrs['paginate'])) {
            $pages = max(1, (int) ceil((clone $query)->count() / $limit));
            $page = max(1, min($page, $pages));
            $query->offset(($page - 1) * $limit);
        } else {
            $page = 1;
        }

        $items = $query->limit($limit)->get();
        $media = Media::whereIn('id', $items->pluck('featured_media_id')->filter()->all())->get()->keyBy('id');

        return ['items' => $items, 'media' => $media, 'filters' => $filters, 'active' => $active, 'type' => $type, 'page' => $page, 'pages' => $pages];
    }

    /** @return array{0: string, 1: string}|null */
    private function parseTerm(string $value): ?array
    {
        return preg_match('/^([a-z0-9_-]+):([^:\s]+)$/', $value, $m) ? [$m[1], $m[2]] : null;
    }

    /** Terminy taksonomii, które mają opublikowane treści tego typu. @return list<array{slug: string, name: string}> */
    private function filters(ContentType $type, Taxonomy $taxonomy): array
    {
        return Term::where('taxonomy_id', $taxonomy->id)
            ->whereHas('contents', fn ($q) => $q->published()->where('content_type_id', $type->id))
            ->orderBy('name')->get()
            ->map(fn (Term $t) => ['slug' => $t->slug, 'name' => $t->name])->all();
    }
}
