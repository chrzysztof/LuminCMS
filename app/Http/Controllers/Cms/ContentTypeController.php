<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\ContentType;
use App\Cms\Importer\Importer;
use App\Cms\Taxonomy\Taxonomy;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Panel własnych typów treści (np. Realizacje, Oferty, Wydarzenia) obok systemowych Stron i Wpisów. */
class ContentTypeController extends Controller
{
    /** Przedrostki zajęte przez panel i trasy systemowe. */
    private const RESERVED = ['cms', 'login', 'logout', 'register', 'dashboard', 'settings', 'theme-assets', 'sitemap', 'robots', 'build', 'storage', 'up', 'install'];

    /** Klucze, których nie wolno użyć (trasy panelu i typy wewnętrzne). */
    private const RESERVED_KEYS = ['part', 'new', 'create', 'edit', 'builder'];

    public function index(): Response
    {
        $types = ContentType::with('taxonomies:taxonomies.id,key')->withCount('contents')->orderByDesc('is_system')->orderBy('id')->get();

        return Inertia::render('content-types/index', [
            'types' => $types->reject(fn (ContentType $t) => $t->key === 'part')->map(fn (ContentType $t) => [
                'key' => $t->key,
                'label' => $t->label,
                'label_plural' => $t->label_plural,
                'icon' => $t->icon,
                'hierarchical' => $t->hierarchical,
                'supports' => $t->supports ?? [],
                'url_prefix' => $t->url_prefix,
                'capability_type' => $t->capability_type ?: 'post',
                'is_system' => $t->is_system,
                'contents_count' => $t->contents_count,
                'taxonomies' => $t->taxonomies->pluck('key')->all(),
            ])->values(),
            'supportOptions' => Importer::SUPPORTS,
            'taxonomyOptions' => Taxonomy::orderBy('label_plural')->get(['key', 'label_plural'])->map(fn ($x) => ['key' => $x->key, 'label' => $x->label_plural])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $type = ContentType::create([
            'key' => $data['key'],
            'label' => $data['label'],
            'label_plural' => $data['label_plural'],
            'icon' => $data['icon'] ?? null,
            'hierarchical' => (bool) ($data['hierarchical'] ?? false),
            'supports' => array_values($data['supports'] ?? ['title', 'editor', 'seo']),
            'url_prefix' => $data['url_prefix'] ?? str_replace('_', '-', $data['key']),
            'capability_type' => $data['capability_type'] ?? 'post',
        ]);
        $type->taxonomies()->sync($this->taxonomyIds($data['taxonomies'] ?? []));

        return $this->back(__('Typ treści dodany.'));
    }

    public function update(Request $request, ContentType $contentType): RedirectResponse
    {
        abort_if($contentType->key === 'part', 404);

        $data = $this->validated($request, $contentType);

        $attributes = [
            'label' => $data['label'],
            'label_plural' => $data['label_plural'],
            'icon' => $data['icon'] ?? null,
            'supports' => array_values($data['supports'] ?? []),
        ];

        // Struktura systemowych typów (Strona, Wpis) jest stała; własne można zmieniać swobodnie.
        if (! $contentType->is_system) {
            $attributes += [
                'hierarchical' => (bool) ($data['hierarchical'] ?? false),
                'url_prefix' => $data['url_prefix'] ?? null,
                'capability_type' => $data['capability_type'] ?? 'post',
            ];
        }

        $contentType->update($attributes);
        $contentType->taxonomies()->sync($this->taxonomyIds($data['taxonomies'] ?? []));

        return $this->back(__('Typ treści zapisany.'));
    }

    public function destroy(ContentType $contentType): RedirectResponse
    {
        abort_if($contentType->is_system || $contentType->key === 'part', 403);

        if ($contentType->contents()->withTrashed()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Najpierw usuń lub przenieś wszystkie treści tego typu (także z kosza).')]);

            return to_route('cms.content-types.index');
        }

        $contentType->delete();

        return $this->back(__('Typ treści usunięty.'));
    }

    private function validated(Request $request, ?ContentType $type): array
    {
        $prefixTaken = function (string $attr, mixed $value, \Closure $fail) use ($type) {
            if (in_array($value, self::RESERVED, true)
                || ContentType::where('url_prefix', $value)->when($type, fn ($q) => $q->where('id', '!=', $type->id))->exists()
                || Taxonomy::where('rewrite_base', $value)->exists()) {
                $fail(__('Ten przedrostek adresu jest zajęty.'));
            }
        };

        return $request->validate([
            'key' => $type ? ['prohibited'] : ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('content_types', 'key'), Rule::notIn(self::RESERVED_KEYS)],
            'label' => ['required', 'string', 'max:255'],
            'label_plural' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'hierarchical' => ['nullable', 'boolean'],
            'supports' => ['nullable', 'array'],
            'supports.*' => ['string', Rule::in(Importer::SUPPORTS)],
            'url_prefix' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $prefixTaken],
            'capability_type' => ['nullable', Rule::in(['post', 'page'])],
            'taxonomies' => ['nullable', 'array'],
            'taxonomies.*' => ['string', Rule::exists('taxonomies', 'key')],
        ]);
    }

    /** @param  list<string>  $keys */
    private function taxonomyIds(array $keys): array
    {
        return Taxonomy::whereIn('key', $keys)->pluck('id')->all();
    }

    private function back(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('cms.content-types.index');
    }
}
