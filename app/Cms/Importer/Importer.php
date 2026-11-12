<?php

namespace App\Cms\Importer;

use App\Cms\Builder\BuilderRenderer;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Fields\FieldValidator;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Cms\Themes\ThemeManager;
use App\Http\Controllers\Cms\FieldGroupController;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Import definicji i treści z JSON (schemat `lumin: 1`).
 *
 * Wszystko dzieje się w jednej transakcji: błąd w dowolnym elemencie albo tryb próbny
 * (dry-run) cofa całość, więc raport pokazuje dokładnie to, co by się stało, bez
 * osobnej, drugiej implementacji walidacji.
 */
class Importer
{
    public const VERSION = 1;

    public const MAX_CONTENTS = 2000;

    public const MAX_TERMS = 5000;

    public const SUPPORTS = ['title', 'editor', 'builder', 'excerpt', 'thumbnail', 'seo', 'author', 'page_attributes'];

    private const STATUSES = [Content::STATUS_DRAFT, Content::STATUS_PENDING, Content::STATUS_PUBLISHED, Content::STATUS_SCHEDULED];

    private const RESERVED = ['cms', 'login', 'logout', 'register', 'dashboard', 'settings', 'theme-assets', 'sitemap', 'robots', 'build', 'storage', 'up'];

    private bool $update = false;

    private ImportReport $report;

    /** @var array<string, array<string, int>> slug => id, per typ treści (do rodziców) */
    private array $contentIds = [];

    public function __construct(
        private readonly FieldGroupController $groups,
        private readonly FieldValidator $validator,
        private readonly ThemeManager $themes,
        private readonly BuilderRenderer $renderer,
    ) {}

    /**
     * @param  'skip'|'update'  $existing  co zrobić z elementami, które już istnieją (po kluczu / slugu)
     */
    public function run(array $data, bool $dryRun, string $existing, ?User $user): ImportReport
    {
        $this->report = new ImportReport($dryRun);
        $this->update = $existing === 'update';
        $this->contentIds = [];

        if (! $this->checkHeader($data)) {
            return $this->report;
        }

        DB::beginTransaction();

        try {
            $this->contentTypes((array) ($data['content_types'] ?? []));
            $this->taxonomies((array) ($data['taxonomies'] ?? []));
            $this->fieldGroups((array) ($data['field_groups'] ?? []));
            $this->contents((array) ($data['contents'] ?? []), $user);
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $this->report->applied() ? DB::commit() : DB::rollBack();

        return $this->report;
    }

    private function checkHeader(array $data): bool
    {
        $version = $data['lumin'] ?? null;

        if (! is_int($version) || $version < 1) {
            $this->report->error(__('Plik'), __('Brak numeru wersji schematu („lumin”). To nie jest plik Lumin CMS.'));

            return false;
        }

        if ($version > self::VERSION) {
            $this->report->error(__('Plik'), __('Plik pochodzi z nowszej wersji schematu (:v). Zaktualizuj Lumin CMS.', ['v' => $version]));

            return false;
        }

        $known = array_intersect(['content_types', 'taxonomies', 'field_groups', 'contents'], array_keys($data));

        foreach ($known as $section) {
            if (! is_array($data[$section]) || ! array_is_list($data[$section])) {
                $this->report->error($section, __('Sekcja musi być listą.'));

                return false;
            }
        }

        if ($known === [] || array_sum(array_map(fn ($s) => count($data[$s]), $known)) === 0) {
            $this->report->error(__('Plik'), __('Plik nie zawiera żadnych danych do zaimportowania.'));

            return false;
        }

        if (count($data['contents'] ?? []) > self::MAX_CONTENTS) {
            $this->report->error('contents', __('Za dużo treści w jednym pliku (maksymalnie :n).', ['n' => self::MAX_CONTENTS]));

            return false;
        }

        $terms = array_sum(array_map(fn ($t) => is_array($t) ? count((array) ($t['terms'] ?? [])) : 0, (array) ($data['taxonomies'] ?? [])));

        if ($terms > self::MAX_TERMS) {
            $this->report->error('taxonomies', __('Za dużo terminów w jednym pliku (maksymalnie :n).', ['n' => self::MAX_TERMS]));

            return false;
        }

        return true;
    }

    // ---------------------------------------------------------------- typy treści

    private function contentTypes(array $items): void
    {
        foreach ($items as $i => $item) {
            $where = __('Typ treści').' #'.($i + 1);
            $item = is_array($item) ? $item : [];

            $v = validator($item, [
                'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
                'label' => ['required', 'string', 'max:255'],
                'label_plural' => ['required', 'string', 'max:255'],
                'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
                'hierarchical' => ['nullable', 'boolean'],
                'supports' => ['nullable', 'array'],
                'supports.*' => ['string', Rule::in(self::SUPPORTS)],
                'url_prefix' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            ]);

            if (! $this->passes($v, $where)) {
                continue;
            }

            $d = $v->validated();
            $where .= ' ('.$d['key'].')';
            $type = ContentType::where('key', $d['key'])->first();

            if ($type?->is_system) {
                $this->report->count('content_types', 'skipped');
                $this->report->note(__('Typ systemowy „:key” pominięto.', ['key' => $d['key']]));

                continue;
            }

            $prefix = $d['url_prefix'] ?? null;

            if ($prefix !== null && ($this->prefixTaken($prefix, $type?->id))) {
                $this->report->error($where, __('Ten przedrostek adresu jest zajęty.'));

                continue;
            }

            $attributes = [
                'label' => $d['label'],
                'label_plural' => $d['label_plural'],
                'icon' => $d['icon'] ?? null,
                'hierarchical' => (bool) ($d['hierarchical'] ?? false),
                'supports' => array_values($d['supports'] ?? ['title', 'editor', 'seo']),
                'url_prefix' => $prefix,
            ];

            if (! $type) {
                ContentType::create(['key' => $d['key']] + $attributes);
                $this->report->count('content_types', 'created');
            } elseif ($this->update) {
                $type->update($attributes);
                $this->report->count('content_types', 'updated');
            } else {
                $this->report->count('content_types', 'skipped');
            }
        }
    }

    private function prefixTaken(string $prefix, ?int $ignoreTypeId): bool
    {
        return in_array($prefix, self::RESERVED, true)
            || ContentType::where('url_prefix', $prefix)->when($ignoreTypeId, fn ($q) => $q->where('id', '!=', $ignoreTypeId))->exists()
            || Taxonomy::where('rewrite_base', $prefix)->exists();
    }

    // ------------------------------------------------------------------ taksonomie

    private function taxonomies(array $items): void
    {
        foreach ($items as $i => $item) {
            $where = __('Taksonomia').' #'.($i + 1);
            $item = is_array($item) ? $item : [];

            $v = validator($item, [
                'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
                'label' => ['required', 'string', 'max:255'],
                'label_plural' => ['required', 'string', 'max:255'],
                'rewrite_base' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'hierarchical' => ['nullable', 'boolean'],
                'content_types' => ['nullable', 'array'],
                'content_types.*' => ['string', Rule::exists('content_types', 'key')],
                'terms' => ['nullable', 'array'],
            ], ['content_types.*.exists' => __('Nieznany typ treści: :input')]);

            if (! $this->passes($v, $where)) {
                continue;
            }

            $d = $v->validated();
            $where .= ' ('.$d['key'].')';
            $taxonomy = Taxonomy::where('key', $d['key'])->first();
            $base = $d['rewrite_base'] ?? $taxonomy?->rewrite_base ?? str_replace('_', '-', $d['key']);

            if (! $taxonomy?->is_system && ($taxonomy === null || $this->update) && $this->baseTaken($base, $taxonomy?->id)) {
                $this->report->error($where, __('Ten przedrostek adresu jest zajęty.'));

                continue;
            }

            if (! $taxonomy) {
                $taxonomy = Taxonomy::create([
                    'key' => $d['key'], 'label' => $d['label'], 'label_plural' => $d['label_plural'],
                    'rewrite_base' => $base, 'hierarchical' => (bool) ($d['hierarchical'] ?? false),
                ]);
                $taxonomy->contentTypes()->sync(ContentType::whereIn('key', $d['content_types'] ?? [])->pluck('id'));
                $this->report->count('taxonomies', 'created');
            } elseif ($this->update && ! $taxonomy->is_system) {
                $taxonomy->update(['label' => $d['label'], 'label_plural' => $d['label_plural'], 'rewrite_base' => $base]
                    + (array_key_exists('hierarchical', $d) && $d['hierarchical'] !== null ? ['hierarchical' => (bool) $d['hierarchical']] : []));
                $taxonomy->contentTypes()->sync(ContentType::whereIn('key', $d['content_types'] ?? [])->pluck('id'));
                $this->report->count('taxonomies', 'updated');
            } else {
                $this->report->count('taxonomies', 'skipped');
            }

            $this->terms($taxonomy, (array) ($d['terms'] ?? []), $where);
        }
    }

    private function baseTaken(string $base, ?int $ignoreId): bool
    {
        return in_array($base, self::RESERVED, true)
            || ContentType::where('url_prefix', $base)->exists()
            || Taxonomy::where('rewrite_base', $base)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists();
    }

    private function terms(Taxonomy $taxonomy, array $items, string $where): void
    {
        $parents = []; // slug => slug rodzica
        $seen = [];

        foreach ($items as $i => $item) {
            $at = $where.' → '.__('termin').' #'.($i + 1);
            $item = is_array($item) ? $item : [];

            $v = validator($item, [
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'description' => ['nullable', 'string', 'max:5000'],
                'parent' => ['nullable', 'string', 'max:255'],
            ]);

            if (! $this->passes($v, $at)) {
                continue;
            }

            $d = $v->validated();
            $slug = $d['slug'] ?? (Str::slug($d['name']) ?: 'termin');

            if (isset($seen[$slug])) {
                $this->report->error($at, __('Slug „:slug” występuje w pliku więcej niż raz.', ['slug' => $slug]));

                continue;
            }

            $seen[$slug] = true;
            $term = Term::where('taxonomy_id', $taxonomy->id)->where('slug', $slug)->first();
            $attributes = ['name' => $d['name'], 'description' => $d['description'] ?? null];

            if (! $term) {
                Term::create($attributes + ['taxonomy_id' => $taxonomy->id, 'slug' => $slug]);
                $this->report->count('terms', 'created');
            } elseif ($this->update) {
                $term->update($attributes);
                $this->report->count('terms', 'updated');
            } else {
                $this->report->count('terms', 'skipped');

                continue; // istniejącego terminu nie przepinamy do innego rodzica
            }

            if (! empty($d['parent'])) {
                $parents[$slug] = $d['parent'];
            }
        }

        if ($parents === []) {
            return;
        }

        if (! $taxonomy->hierarchical) {
            $this->report->note(__('Taksonomia „:key” jest płaska, więc rodziców terminów pominięto.', ['key' => $taxonomy->key]));

            return;
        }

        $ids = Term::where('taxonomy_id', $taxonomy->id)->pluck('id', 'slug');

        foreach ($parents as $slug => $parentSlug) {
            if (! isset($ids[$parentSlug]) || $parentSlug === $slug || $this->loops($slug, $parentSlug, $parents)) {
                $this->report->error($where.' → '.$slug, __('Nieprawidłowy rodzic terminu: :parent.', ['parent' => $parentSlug]));

                continue;
            }

            Term::whereKey($ids[$slug])->update(['parent_id' => $ids[$parentSlug]]);
        }
    }

    /** Czy przypięcie `$slug` pod `$parent` zamknęłoby pętlę w drzewie z pliku. */
    private function loops(string $slug, string $parent, array $parents): bool
    {
        for ($hops = 0; $parent !== null && $hops < 1000; $hops++) {
            if ($parent === $slug) {
                return true;
            }

            $parent = $parents[$parent] ?? null;
        }

        return false;
    }

    // ----------------------------------------------------------------- grupy pól

    private function fieldGroups(array $items): void
    {
        foreach ($items as $i => $item) {
            $where = __('Grupa pól').' #'.($i + 1);

            if (! is_array($item)) {
                $this->report->error($where, __('Nieprawidłowy format.'));

                continue;
            }

            $existing = isset($item['key']) && is_string($item['key']) ? FieldGroup::where('key', $item['key'])->first() : null;

            if ($existing && ! $this->update) {
                $this->report->count('field_groups', 'skipped');

                continue;
            }

            try {
                $attributes = $this->groups->validateData($item, $existing, enforceUniqueKey: false);
            } catch (ValidationException $e) {
                $this->report->error($where.(isset($item['key']) && is_string($item['key']) ? " ({$item['key']})" : ''), (string) collect($e->errors())->flatten()->first());

                continue;
            }

            if ($existing) {
                $existing->update($attributes);
                $this->report->count('field_groups', 'updated');
            } else {
                FieldGroup::create($attributes + ['menu_order' => (int) FieldGroup::max('menu_order') + 1]);
                $this->report->count('field_groups', 'created');
            }
        }
    }

    // -------------------------------------------------------------------- treści

    private function contents(array $items, ?User $user): void
    {
        $pending = []; // [content, parentSlug, where]

        foreach ($items as $i => $item) {
            $where = __('Treść').' #'.($i + 1);
            $item = is_array($item) ? $item : [];

            $type = ContentType::where('key', (string) ($item['type'] ?? ''))->first();

            if (! $type || $type->key === 'part') {
                $this->report->error($where, __('Nieznany typ treści: :type', ['type' => (string) ($item['type'] ?? '')]));

                continue;
            }

            $themeTemplates = array_column($this->themes->templatesFor($type), 'key');

            $v = validator($item, [
                'title' => ['required', 'string', 'max:255'],
                'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'status' => ['nullable', Rule::in(self::STATUSES)],
                'excerpt' => ['nullable', 'string', 'max:5000'],
                'template' => ['nullable', 'string', Rule::in(['default', ...$themeTemplates])],
                'published_at' => ['nullable', 'date'],
                'parent' => ['nullable', 'string', 'max:255'],
                'menu_order' => ['nullable', 'integer'],
                'fields' => ['nullable', 'array'],
                'seo' => ['nullable', 'array'],
                'terms' => ['nullable', 'array'],
                'builder_data' => ['nullable', 'array'],
            ]);

            if (! $this->passes($v, $where)) {
                continue;
            }

            $d = $v->validated();
            $slug = $d['slug'] ?? (Str::slug($d['title']) ?: 'bez-tytulu');
            $where .= " ({$type->key}/{$slug})";
            $status = $d['status'] ?? Content::STATUS_DRAFT;

            if ($status === Content::STATUS_SCHEDULED && (empty($d['published_at']) || now()->gte($d['published_at']))) {
                $this->report->error($where, __('Treść zaplanowana wymaga daty publikacji w przyszłości.'));

                continue;
            }

            $content = Content::where('content_type_id', $type->id)->where('slug', $slug)->first();

            if ($content && ! $this->update) {
                $this->report->count('contents', 'skipped');
                $this->contentIds[$type->key][$slug] = $content->id;

                continue;
            }

            $termIds = $this->resolveTerms($type, (array) ($d['terms'] ?? []), $where);

            if ($termIds === null) {
                continue;
            }

            $context = ['content_type' => $type->key, 'template' => $d['template'] ?? 'default', 'parent' => null, 'status' => $status, 'term' => $termIds];
            $definitions = FieldGroup::forContext($context)->flatMap(fn (FieldGroup $g) => $g->fields ?? [])->values()->all();

            try {
                $fields = $this->validator->validate($definitions, (array) ($d['fields'] ?? []));
            } catch (ValidationException $e) {
                $this->report->error($where, __('Pola własne').': '.collect($e->errors())->flatten()->first());

                continue;
            }

            $attributes = [
                'title' => $d['title'],
                'status' => $status,
                'excerpt' => $d['excerpt'] ?? null,
                'template' => ($d['template'] ?? 'default') === 'default' ? null : $d['template'],
                'published_at' => match ($status) {
                    Content::STATUS_PUBLISHED => $d['published_at'] ?? $content?->published_at ?? now(),
                    Content::STATUS_SCHEDULED => $d['published_at'],
                    default => $d['published_at'] ?? $content?->published_at,
                },
                'menu_order' => $d['menu_order'] ?? 0,
                'fields' => $fields,
                'seo' => $this->cleanSeo((array) ($d['seo'] ?? [])),
            ];

            if (! empty($d['builder_data'])) {
                $attributes['builder_data'] = $d['builder_data'];
                $attributes['html_cache'] = $this->renderer->render($d['builder_data']);
            }

            if ($content) {
                $content->update($attributes);
                $this->report->count('contents', 'updated');
            } else {
                // Slug unikalny także wśród usuniętych (jak w ContentController::uniqueSlug).
                $unique = $slug;

                for ($n = 2; Content::withTrashed()->where('content_type_id', $type->id)->where('slug', $unique)->exists(); $n++) {
                    $unique = $slug.'-'.$n;
                }

                $content = Content::create($attributes + ['content_type_id' => $type->id, 'slug' => $unique, 'author_id' => $user?->id]);
                $this->report->count('contents', 'created');

                if ($unique !== $slug) {
                    $this->report->note(__('Slug „:slug” był zajęty (także w koszu), użyto „:unique”.', ['slug' => $slug, 'unique' => $unique]));
                }
            }

            $content->terms()->sync($termIds);
            $this->contentIds[$type->key][$slug] = $content->id;

            if (! empty($d['parent'])) {
                $pending[] = [$content, $d['parent'], $where];
            }
        }

        foreach ($pending as [$content, $parentSlug, $where]) {
            $type = $content->type;
            $parentId = $this->contentIds[$type->key][$parentSlug]
                ?? Content::where('content_type_id', $type->id)->where('slug', $parentSlug)->value('id');

            if (! $type->hierarchical || ! $parentId || $parentId === $content->id || $this->descends($parentId, $content->id)) {
                $this->report->error($where, __('Nieprawidłowy rodzic treści: :parent.', ['parent' => $parentSlug]));

                continue;
            }

            $content->update(['parent_id' => $parentId]);
        }
    }

    /** Czy `$id` leży w poddrzewie `$ancestor` (zabezpieczenie przed pętlą rodziców). */
    private function descends(int $id, int $ancestor): bool
    {
        for ($hops = 0; $id && $hops < 100; $hops++) {
            if ($id === $ancestor) {
                return true;
            }

            $id = (int) Content::whereKey($id)->value('parent_id');
        }

        return false;
    }

    /** @return list<int>|null null, gdy któryś termin nie istnieje (błąd zapisany w raporcie) */
    private function resolveTerms(ContentType $type, array $byTaxonomy, string $where): ?array
    {
        $allowed = $type->taxonomies()->pluck('taxonomies.id', 'taxonomies.key');
        $ids = [];

        foreach ($byTaxonomy as $taxonomyKey => $slugs) {
            if (! isset($allowed[$taxonomyKey])) {
                $this->report->error($where, __('Taksonomia „:key” nie jest przypisana do tego typu treści.', ['key' => (string) $taxonomyKey]));

                return null;
            }

            foreach ((array) $slugs as $slug) {
                $id = Term::where('taxonomy_id', $allowed[$taxonomyKey])->where('slug', (string) $slug)->value('id');

                if (! $id) {
                    $this->report->error($where, __('Nie ma terminu „:slug” w taksonomii „:key”.', ['slug' => (string) $slug, 'key' => $taxonomyKey]));

                    return null;
                }

                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function cleanSeo(array $seo): ?array
    {
        $clean = [];

        foreach (['title' => 200, 'description' => 400, 'og_title' => 200, 'og_description' => 400, 'focus_keyword' => 100] as $key => $max) {
            if (isset($seo[$key]) && is_string($seo[$key]) && trim($seo[$key]) !== '') {
                $clean[$key] = Str::limit(trim($seo[$key]), $max, '');
            }
        }

        if (isset($seo['canonical']) && is_string($seo['canonical']) && preg_match('~^https?://\S+$~i', $seo['canonical']) && strlen($seo['canonical']) <= 500) {
            $clean['canonical'] = $seo['canonical'];
        }

        foreach (['noindex', 'nofollow'] as $flag) {
            if (! empty($seo[$flag])) {
                $clean[$flag] = true;
            }
        }

        return $clean ?: null;
    }

    private function passes($validator, string $where): bool
    {
        if ($validator->fails()) {
            $this->report->error($where, (string) $validator->errors()->first());

            return false;
        }

        return true;
    }
}
