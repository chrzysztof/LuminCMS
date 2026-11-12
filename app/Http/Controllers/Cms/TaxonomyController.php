<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\ContentType;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Panel taksonomii: terminy (kategorie, tagi i własne) oraz definicje własnych taksonomii. */
class TaxonomyController extends Controller
{
    public function index(Request $request): Response
    {
        $taxonomies = Taxonomy::with('contentTypes:id,key')->withCount('terms')->orderByDesc('is_system')->orderBy('label_plural')->get();
        $current = $taxonomies->firstWhere('key', $request->string('taxonomy')->toString()) ?? $taxonomies->first();

        return Inertia::render('taxonomies/index', [
            'taxonomies' => $taxonomies->map(fn (Taxonomy $t) => [
                'id' => $t->id,
                'key' => $t->key,
                'label' => $t->label,
                'label_plural' => $t->label_plural,
                'hierarchical' => $t->hierarchical,
                'rewrite_base' => $t->rewrite_base,
                'is_system' => $t->is_system,
                'terms_count' => $t->terms_count,
                'content_types' => $t->contentTypes->pluck('key')->all(),
            ])->values(),
            'current' => $current?->key,
            'terms' => $current ? $current->flatTerms() : [],
            'typeOptions' => ContentType::orderBy('label_plural')->get(['key', 'label_plural'])->map(fn ($c) => ['key' => $c->key, 'label' => $c->label_plural])->values(),
            'canManage' => $request->user()->can('manage_options'),
        ]);
    }

    public function storeTerm(Request $request, Taxonomy $taxonomy): RedirectResponse
    {
        $this->saveTerm($request, $taxonomy, new Term(['taxonomy_id' => $taxonomy->id]));

        return $this->back($taxonomy, __('Termin dodany.'));
    }

    public function updateTerm(Request $request, Taxonomy $taxonomy, Term $term): RedirectResponse
    {
        abort_unless($term->taxonomy_id === $taxonomy->id, 404);

        $this->saveTerm($request, $taxonomy, $term);

        return $this->back($taxonomy, __('Termin zapisany.'));
    }

    public function destroyTerm(Taxonomy $taxonomy, Term $term): RedirectResponse
    {
        abort_unless($term->taxonomy_id === $taxonomy->id, 404);

        // Dzieci przechodzą poziom wyżej; przypisania do treści znikają razem z terminem (kaskada).
        $term->children()->update(['parent_id' => $term->parent_id]);
        $term->delete();

        return $this->back($taxonomy, __('Termin usunięty.'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTaxonomy($request, null);

        $taxonomy = Taxonomy::create([
            'key' => $data['key'],
            'label' => $data['label'],
            'label_plural' => $data['label_plural'],
            'rewrite_base' => $data['rewrite_base'] ?? str_replace('_', '-', $data['key']),
            'hierarchical' => $data['hierarchical'] ?? false,
        ]);
        $taxonomy->contentTypes()->sync($this->typeIds($data['content_types'] ?? []));

        return $this->back($taxonomy, __('Taksonomia dodana.'));
    }

    public function update(Request $request, Taxonomy $taxonomy): RedirectResponse
    {
        $data = $this->validateTaxonomy($request, $taxonomy);

        $attributes = ['label' => $data['label'], 'label_plural' => $data['label_plural']];

        if (! empty($data['rewrite_base'])) {
            $attributes['rewrite_base'] = $data['rewrite_base'];
        }

        // Zmiana typu (płaska/hierarchiczna) tylko dla własnych taksonomii; płaska gubi rodziców.
        if (! $taxonomy->is_system && array_key_exists('hierarchical', $data)) {
            $attributes['hierarchical'] = (bool) $data['hierarchical'];

            if (! $attributes['hierarchical']) {
                $taxonomy->terms()->update(['parent_id' => null]);
            }
        }

        $taxonomy->update($attributes);
        $taxonomy->contentTypes()->sync($this->typeIds($data['content_types'] ?? []));

        return $this->back($taxonomy, __('Taksonomia zapisana.'));
    }

    public function destroy(Taxonomy $taxonomy): RedirectResponse
    {
        abort_if($taxonomy->is_system, 403);

        $taxonomy->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Taksonomia usunięta.')]);

        return to_route('cms.taxonomies.index');
    }

    private function saveTerm(Request $request, Taxonomy $taxonomy, Term $term): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('terms', 'id')->where('taxonomy_id', $taxonomy->id)],
        ]);

        $parentId = $taxonomy->hierarchical ? ($data['parent_id'] ?? null) : null;

        if ($term->exists && $parentId) {
            // Rodzicem nie może być sam termin ani żaden jego potomek (pętla w drzewie).
            if ($parentId === $term->id || in_array($parentId, $this->descendants($term), true)) {
                throw ValidationException::withMessages(['parent_id' => __('Termin nie może być własnym rodzicem ani potomkiem.')]);
            }
        }

        $term->fill([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($taxonomy, ($data['slug'] ?? null) ?: $data['name'], $term),
            'description' => $data['description'] ?? null,
            'parent_id' => $parentId,
        ])->save();
    }

    /** @return list<int> */
    private function descendants(Term $term): array
    {
        $ids = [];
        $queue = [$term->id];

        while ($queue) {
            $children = Term::whereIn('parent_id', $queue)->pluck('id')->all();
            $ids = [...$ids, ...$children];
            $queue = $children;
        }

        return $ids;
    }

    private function uniqueSlug(Taxonomy $taxonomy, string $source, Term $term): string
    {
        $base = Str::slug($source) ?: 'termin';
        $slug = $base;
        $i = 2;

        while (Term::where('taxonomy_id', $taxonomy->id)->where('slug', $slug)
            ->when($term->exists, fn ($q) => $q->where('id', '!=', $term->id))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function validateTaxonomy(Request $request, ?Taxonomy $taxonomy): array
    {
        return $request->validate([
            'key' => $taxonomy ? ['prohibited'] : ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('taxonomies', 'key')],
            'label' => ['required', 'string', 'max:255'],
            'label_plural' => ['required', 'string', 'max:255'],
            'rewrite_base' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('taxonomies', 'rewrite_base')->ignore($taxonomy?->id),
                // Przedrostek nie może zasłaniać archiwum typu treści ani panelu.
                fn ($attr, $value, $fail) => (ContentType::where('url_prefix', $value)->exists() || in_array($value, ['cms', 'login', 'register', 'dashboard', 'settings', 'theme-assets', 'sitemap', 'robots'], true))
                    ? $fail(__('Ten przedrostek jest zajęty.')) : null],
            'hierarchical' => ['nullable', 'boolean'],
            'content_types' => ['nullable', 'array'],
            'content_types.*' => ['string', Rule::exists('content_types', 'key')],
        ]);
    }

    /** @param  list<string>  $keys */
    private function typeIds(array $keys): array
    {
        return ContentType::whereIn('key', $keys)->pluck('id')->all();
    }

    private function back(Taxonomy $taxonomy, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('cms.taxonomies.index', ['taxonomy' => $taxonomy->key]);
    }
}
