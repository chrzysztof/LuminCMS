<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Media\Media;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    private const STATUSES = [Content::STATUS_DRAFT, Content::STATUS_PUBLISHED, Content::STATUS_SCHEDULED];

    public function __construct(
        private readonly FieldRegistry $registry,
        private readonly FieldValidator $validator,
    ) {}

    public function index(Request $request, ContentType $type): Response
    {
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();

        $query = Content::query()->where('content_type_id', $type->id)->with('author:id,name')->latest('updated_at');

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $counts = Content::where('content_type_id', $type->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('content/index', [
            'type' => $this->typeData($type),
            'items' => $query->paginate(20)->withQueryString()->through(fn (Content $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'status' => $c->status,
                'author' => $c->author?->name,
                'updated_at' => $c->updated_at?->toIso8601String(),
                'published_at' => $c->published_at?->toIso8601String(),
            ]),
            'counts' => [
                'all' => $counts->sum(),
                Content::STATUS_PUBLISHED => $counts[Content::STATUS_PUBLISHED] ?? 0,
                Content::STATUS_DRAFT => $counts[Content::STATUS_DRAFT] ?? 0,
                Content::STATUS_SCHEDULED => $counts[Content::STATUS_SCHEDULED] ?? 0,
            ],
            'filters' => ['status' => $status, 'search' => $search],
        ]);
    }

    public function create(ContentType $type): Response
    {
        return $this->form($type, null);
    }

    public function edit(ContentType $type, Content $content): Response
    {
        abort_unless($content->content_type_id === $type->id, 404);

        return $this->form($type, $content);
    }

    public function store(Request $request, ContentType $type): RedirectResponse
    {
        $content = new Content(['content_type_id' => $type->id, 'author_id' => $request->user()->id]);

        $this->save($request, $type, $content);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zapisano.']);

        return to_route('cms.content.edit', [$type->key, $content]);
    }

    public function update(Request $request, ContentType $type, Content $content): RedirectResponse
    {
        abort_unless($content->content_type_id === $type->id, 404);

        $this->save($request, $type, $content);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zapisano.']);

        return back();
    }

    public function destroy(ContentType $type, Content $content): RedirectResponse
    {
        abort_unless($content->content_type_id === $type->id, 404);

        $content->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Przeniesiono do kosza.']);

        return to_route('cms.content.index', $type->key);
    }

    private function form(ContentType $type, ?Content $content): Response
    {
        $context = [
            'content_type' => $type->key,
            'template' => $content?->template ?: 'default',
            'parent' => $content?->parent_id,
            'status' => $content?->status ?? Content::STATUS_DRAFT,
            'term' => $content ? $content->terms()->pluck('terms.id')->all() : [],
        ];

        $groups = FieldGroup::forContext($context)->map(fn (FieldGroup $g) => [
            'id' => $g->id,
            'key' => $g->key,
            'title' => $g->title,
            'position' => $g->position ?: 'normal',
            'style' => $g->style ?: 'default',
            'label_placement' => $g->label_placement ?: 'top',
            'fields' => collect($g->fields ?? [])->map(fn ($f) => $this->registry->resolveField($f))->values()->all(),
        ])->values();

        $values = $content?->fields ?? [];
        $allFields = $groups->pluck('fields')->flatten(1);

        // Szyfrogramów haseł nie wysyłamy do przeglądarki.
        foreach ($allFields->where('type', 'password') as $field) {
            unset($values[$field['name']]);
        }

        $media = $this->referencedMedia($allFields->all(), $values, $content?->featured_media_id);

        // Usuń z formularza odwołania do plików, których już nie ma w bibliotece.
        foreach ($allFields->whereIn('type', ['image', 'file', 'gallery']) as $field) {
            $name = $field['name'];

            if (! array_key_exists($name, $values)) {
                continue;
            }

            $values[$name] = $field['type'] === 'gallery'
                ? array_values(array_filter((array) $values[$name], fn ($id) => isset($media[$id])))
                : (isset($media[$values[$name]]) ? $values[$name] : null);
        }

        return Inertia::render('content/edit', [
            'type' => $this->typeData($type),
            'content' => $content ? [
                'id' => $content->id,
                'title' => $content->title,
                'slug' => $content->slug,
                'status' => $content->status,
                'excerpt' => $content->excerpt,
                'parent_id' => $content->parent_id,
                'featured_media_id' => $content->featured_media_id,
                'published_at' => $content->published_at?->format('Y-m-d\TH:i'),
                'updated_at' => $content->updated_at?->toIso8601String(),
            ] : null,
            'values' => (object) $values,
            'groups' => $groups,
            'media' => (object) $media,
            'parents' => $type->hierarchical
                ? Content::where('content_type_id', $type->id)->when($content, fn ($q) => $q->where('id', '!=', $content->id))
                    ->orderBy('title')->get(['id', 'title'])
                : [],
        ]);
    }

    private function save(Request $request, ContentType $type, Content $content): void
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'excerpt' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('contents', 'id')->where('content_type_id', $type->id)],
            'featured_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
            'published_at' => ['nullable', 'date', Rule::requiredIf($request->input('status') === Content::STATUS_SCHEDULED)],
            'fields' => ['nullable', 'array'],
        ]);

        if ($content->exists && ! empty($data['parent_id']) && $data['parent_id'] === $content->id) {
            throw ValidationException::withMessages(['parent_id' => 'Treść nie może być własnym rodzicem.']);
        }

        $context = [
            'content_type' => $type->key,
            'template' => $content->template ?: 'default',
            'parent' => $data['parent_id'] ?? null,
            'status' => $data['status'],
            'term' => $content->exists ? $content->terms()->pluck('terms.id')->all() : [],
        ];

        $definitions = FieldGroup::forContext($context)->flatMap(fn (FieldGroup $g) => $g->fields ?? [])->values()->all();

        try {
            $fields = $this->validator->validate($definitions, $data['fields'] ?? []);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ["fields.{$k}" => $m])->all());
        }

        // Puste pole hasła oznacza: zostaw dotychczasową wartość.
        foreach ($definitions as $definition) {
            if (($definition['type'] ?? null) === 'password' && ($fields[$definition['name']] ?? null) === null
                && isset($content->fields[$definition['name']])) {
                $fields[$definition['name']] = $content->fields[$definition['name']];
            }
        }

        $publishedAt = match ($data['status']) {
            Content::STATUS_PUBLISHED => $content->published_at ?? now(),
            Content::STATUS_SCHEDULED => $data['published_at'],
            default => $content->published_at,
        };

        $content->fill([
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($type, ($data['slug'] ?? null) ?: $data['title'], $content),
            'status' => $data['status'],
            'excerpt' => $data['excerpt'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'featured_media_id' => $data['featured_media_id'] ?? null,
            'published_at' => $publishedAt,
            'fields' => $fields,
        ])->save();
    }

    private function uniqueSlug(ContentType $type, string $source, Content $content): string
    {
        $base = Str::slug($source) ?: 'bez-tytulu';
        $slug = $base;
        $i = 2;

        while (Content::withTrashed()->where('content_type_id', $type->id)->where('slug', $slug)
            ->when($content->exists, fn ($q) => $q->where('id', '!=', $content->id))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function typeData(ContentType $type): array
    {
        return [
            'key' => $type->key,
            'label' => $type->label,
            'label_plural' => $type->label_plural,
            'hierarchical' => $type->hierarchical,
            'supports' => $type->supports ?? [],
            'url_prefix' => $type->url_prefix,
        ];
    }

    /** Obiekty mediów powiązane z treścią (do podglądu w polach Image/File/Gallery). */
    private function referencedMedia(array $fields, array $values, ?int $featuredId): array
    {
        $ids = $featuredId ? [$featuredId] : [];

        foreach ($fields as $field) {
            if (! in_array($field['type'], ['image', 'file', 'gallery'], true)) {
                continue;
            }

            $value = $values[$field['name']] ?? null;
            $ids = [...$ids, ...(is_array($value) ? $value : [$value])];
        }

        $ids = array_values(array_unique(array_filter($ids, 'is_numeric')));

        return Media::whereIn('id', $ids)->get()->mapWithKeys(fn (Media $m) => [$m->id => $m->asArray('medium')])->all();
    }
}
