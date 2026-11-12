<?php

namespace App\Cms\Taxonomy;

use App\Cms\Content\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taxonomy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'hierarchical' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function contentTypes(): BelongsToMany
    {
        return $this->belongsToMany(ContentType::class, 'taxonomy_content_type');
    }

    /**
     * Terminy w kolejności drzewa (rodzic, potem jego dzieci) z głębokością do wcięć;
     * w taksonomiach płaskich po prostu alfabetycznie.
     *
     * @return list<array{id: int, name: string, slug: string, description: ?string, parent_id: ?int, depth: int, count: int}>
     */
    public function flatTerms(): array
    {
        $terms = $this->terms()->withCount('contents')->orderBy('name')->get();
        $byParent = $terms->groupBy(fn (Term $t) => $this->hierarchical ? ($t->parent_id ?? 0) : 0);
        $out = [];

        $walk = function (int $parent, int $depth) use (&$walk, &$out, $byParent): void {
            foreach ($byParent[$parent] ?? [] as $term) {
                $out[] = [
                    'id' => $term->id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description,
                    'parent_id' => $term->parent_id,
                    'depth' => $depth,
                    'count' => $term->contents_count,
                ];

                if ($this->hierarchical) {
                    $walk($term->id, $depth + 1);
                }
            }
        };
        $walk(0, 0);

        return $out;
    }
}
