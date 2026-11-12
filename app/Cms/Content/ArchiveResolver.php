<?php

namespace App\Cms\Content;

use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Publiczne listy: archiwum typu treści (np. /blog) i strona terminu taksonomii (np. /kategoria/nowosci).
 */
class ArchiveResolver
{
    public const PER_PAGE = 10;

    /** @return array{kind: 'archive', type: ContentType}|array{kind: 'taxonomy', taxonomy: Taxonomy, term: Term}|null */
    public function find(string $path): ?array
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));

        if ($segments === []) {
            return null;
        }

        if (count($segments) === 1) {
            $type = ContentType::where('url_prefix', $segments[0])->where('hierarchical', false)->first();

            if ($type) {
                return ['kind' => 'archive', 'type' => $type];
            }
        }

        if (count($segments) === 2) {
            $taxonomy = Taxonomy::where('rewrite_base', $segments[0])
                ->orWhere(fn ($q) => $q->whereNull('rewrite_base')->where('key', str_replace('-', '_', $segments[0])))
                ->first();
            $term = $taxonomy?->terms()->where('slug', $segments[1])->first();

            if ($taxonomy && $term) {
                return ['kind' => 'taxonomy', 'taxonomy' => $taxonomy, 'term' => $term];
            }
        }

        return null;
    }

    public function urlForArchive(ContentType $type): string
    {
        return url((string) $type->url_prefix);
    }

    public function urlForTerm(Term $term): string
    {
        $term->loadMissing('taxonomy');

        return url($term->taxonomy->base().'/'.$term->slug);
    }

    /** Opublikowane treści typu, od najnowszych. */
    public function itemsOfType(ContentType $type): LengthAwarePaginator
    {
        return $this->paginate(Content::published()->where('content_type_id', $type->id));
    }

    /** Opublikowane treści oznaczone terminem (z dziećmi terminu w taksonomiach hierarchicznych). */
    public function itemsOfTerm(Term $term): LengthAwarePaginator
    {
        $ids = [$term->id];
        $queue = [$term->id];

        while ($term->taxonomy->hierarchical && $queue) {
            $queue = Term::whereIn('parent_id', $queue)->pluck('id')->all();
            $ids = [...$ids, ...$queue];
        }

        return $this->paginate(Content::published()->whereHas('terms', fn ($q) => $q->whereIn('terms.id', $ids)));
    }

    private function paginate($query): LengthAwarePaginator
    {
        return $query->with('type', 'parent')->orderByDesc('published_at')->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
    }
}
