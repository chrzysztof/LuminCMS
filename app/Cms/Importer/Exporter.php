<?php

namespace App\Cms\Importer;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;

/** Eksport w formacie rozumianym przez Importer (`lumin: 1`). */
class Exporter
{
    /**
     * @param  list<string>  $sections  content_types | taxonomies | field_groups | contents
     * @param  list<string>  $types  klucze typów treści, których treści eksportujemy (puste = wszystkie)
     */
    public function build(array $sections, array $types = []): array
    {
        $out = ['lumin' => Importer::VERSION, 'generator' => 'Lumin CMS', 'exported_at' => now()->toIso8601String()];

        if (in_array('content_types', $sections, true)) {
            $out['content_types'] = ContentType::where('is_system', false)->orderBy('id')->get()->map(fn (ContentType $t) => array_filter([
                'key' => $t->key, 'label' => $t->label, 'label_plural' => $t->label_plural, 'icon' => $t->icon,
                'hierarchical' => $t->hierarchical, 'supports' => $t->supports ?? [], 'url_prefix' => $t->url_prefix,
            ], fn ($v) => $v !== null))->all();
        }

        if (in_array('taxonomies', $sections, true)) {
            $out['taxonomies'] = Taxonomy::with('contentTypes:content_types.id,key')->orderBy('id')->get()->map(fn (Taxonomy $x) => [
                'key' => $x->key, 'label' => $x->label, 'label_plural' => $x->label_plural,
                'rewrite_base' => $x->base(), 'hierarchical' => $x->hierarchical,
                'content_types' => $x->contentTypes->pluck('key')->values()->all(),
                'terms' => $this->terms($x),
            ])->all();
        }

        if (in_array('field_groups', $sections, true)) {
            $out['field_groups'] = FieldGroup::orderBy('menu_order')->orderBy('id')->get()->map(fn (FieldGroup $g) => [
                'key' => $g->key, 'title' => $g->title, 'fields' => $g->fields ?? [], 'location_rules' => $g->location_rules ?? [],
                'position' => $g->position ?: 'normal', 'style' => $g->style ?: 'default',
                'label_placement' => $g->label_placement ?: 'top', 'instruction_placement' => $g->instruction_placement ?: 'label',
                'active' => $g->active,
            ])->all();
        }

        if (in_array('contents', $sections, true)) {
            $out['contents'] = $this->contents($types);
        }

        return $out;
    }

    private function terms(Taxonomy $taxonomy): array
    {
        $terms = Term::where('taxonomy_id', $taxonomy->id)->orderBy('id')->get();
        $slugs = $terms->pluck('slug', 'id');

        return $terms->map(fn (Term $t) => array_filter([
            'name' => $t->name, 'slug' => $t->slug, 'description' => $t->description,
            'parent' => $t->parent_id ? ($slugs[$t->parent_id] ?? null) : null,
        ], fn ($v) => $v !== null && $v !== ''))->values()->all();
    }

    private function contents(array $types): array
    {
        $query = Content::with(['type:id,key', 'terms.taxonomy:id,key'])
            ->whereHas('type', fn ($q) => $q->where('key', '!=', 'part')->when($types !== [], fn ($q) => $q->whereIn('key', $types)))
            ->orderBy('content_type_id')->orderBy('id');

        $slugs = Content::withTrashed()->pluck('slug', 'id');

        return $query->get()->map(fn (Content $c) => array_filter([
            'type' => $c->type->key,
            'title' => $c->title,
            'slug' => $c->slug,
            'status' => $c->status,
            'excerpt' => $c->excerpt,
            'template' => $c->template,
            'published_at' => $c->published_at?->toIso8601String(),
            'parent' => $c->parent_id ? ($slugs[$c->parent_id] ?? null) : null,
            'menu_order' => $c->menu_order ?: null,
            'fields' => $c->fields ?: null,
            'seo' => $c->seo ?: null,
            'terms' => $c->terms->groupBy(fn (Term $t) => $t->taxonomy->key)->map(fn ($g) => $g->pluck('slug')->values()->all())->all() ?: null,
            'builder_data' => $c->builder_data ?: null,
        ], fn ($v) => $v !== null && $v !== ''))->values()->all();
    }
}
