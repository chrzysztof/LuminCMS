<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Menus\Menu;
use App\Cms\Menus\MenuItem;
use App\Cms\Menus\MenuRenderer;
use App\Cms\Taxonomy\Term;
use App\Cms\Themes\Parts;
use App\Cms\Themes\ThemeManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Wygląd → Menu: wielopoziomowe menu z megamenu, zapisywane w całości jako płaska lista pozycji z głębokością. */
class MenuController extends Controller
{
    public function __construct(
        private readonly ThemeManager $themes,
        private readonly Parts $parts,
        private readonly MenuRenderer $renderer,
    ) {}

    public function index(Request $request): Response
    {
        $menus = Menu::orderBy('name')->get(['id', 'name', 'slug', 'location']);
        $current = $menus->firstWhere('id', (int) $request->query('menu')) ?? $menus->first();
        $partTypeId = $this->parts->type()->id;

        return Inertia::render('menus/index', [
            'menus' => $menus,
            'menu' => $current ? [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
                'location' => $current->location,
                'items' => $this->flatItems($current),
            ] : null,
            'locations' => $this->themes->active()->menuLocations(),
            'sources' => [
                'contents' => Content::published()->where('content_type_id', '!=', $partTypeId)->with('type:id,label')
                    ->orderBy('title')->limit(1000)->get(['id', 'title', 'content_type_id'])
                    ->map(fn (Content $c) => ['id' => $c->id, 'title' => $c->title, 'group' => $c->type->label])->values(),
                'terms' => Term::with('taxonomy:id,label')->orderBy('name')->limit(1000)->get(['id', 'name', 'taxonomy_id'])
                    ->map(fn (Term $t) => ['id' => $t->id, 'title' => $t->name, 'group' => $t->taxonomy->label])->values(),
                'parts' => Content::where('content_type_id', $partTypeId)->orderBy('title')->get(['id', 'title'])
                    ->map(fn (Content $c) => ['id' => $c->id, 'title' => $c->title])->values(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $menu = Menu::create(['name' => $data['name'], 'slug' => $this->uniqueSlug($data['name'])]);

        return redirect()->route('cms.menus.index', ['menu' => $menu->id]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $locations = array_column($this->themes->active()->menuLocations(), 'key');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', Rule::in($locations)],
            'items' => ['array', 'max:300'],
            'items.*.type' => ['required', Rule::in(MenuItem::TYPES)],
            'items.*.label' => ['nullable', 'string', 'max:150'],
            'items.*.content_id' => ['nullable', 'integer', Rule::exists('contents', 'id')->whereNull('deleted_at')],
            'items.*.term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'items.*.url' => ['nullable', 'string', 'max:500'],
            'items.*.new_tab' => ['boolean'],
            'items.*.depth' => ['required', 'integer', 'min:0', 'max:'.(MenuItem::MAX_DEPTH - 1)],
            'items.*.mega' => ['boolean'],
            'items.*.mega_layout' => ['nullable', Rule::in(MenuItem::LAYOUTS)],
            'items.*.mega_part_id' => ['nullable', 'integer', Rule::exists('contents', 'id')->where('content_type_id', $this->parts->type()->id)->whereNull('deleted_at')],
        ]);

        $items = $data['items'] ?? [];
        $this->validateItems($items);

        DB::transaction(function () use ($menu, $data, $items) {
            if (! empty($data['location'])) {
                Menu::where('location', $data['location'])->where('id', '!=', $menu->id)->update(['location' => null]);
            }

            $menu->update(['name' => $data['name'], 'location' => $data['location'] ?? null]);
            $menu->items()->delete();

            $stack = [];

            foreach ($items as $i => $row) {
                $depth = (int) $row['depth'];
                $type = $row['type'];
                $top = $depth === 0;

                $item = $menu->items()->create([
                    'parent_id' => $depth > 0 ? $stack[$depth - 1] : null,
                    'position' => $i,
                    'type' => $type,
                    'label' => ($row['label'] ?? '') !== '' ? $row['label'] : null,
                    'content_id' => $type === 'content' ? $row['content_id'] : null,
                    'term_id' => $type === 'term' ? $row['term_id'] : null,
                    'url' => $type === 'custom' ? trim((string) $row['url']) : null,
                    'new_tab' => $type !== 'heading' && ! empty($row['new_tab']),
                    'mega' => $top && ! empty($row['mega']),
                    'mega_layout' => $row['mega_layout'] ?? 'container',
                    'mega_part_id' => $top && ! empty($row['mega']) ? ($row['mega_part_id'] ?? null) : null,
                ]);

                $stack[$depth] = $item->id;
            }
        });

        return back();
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();

        return redirect()->route('cms.menus.index');
    }

    /** Spójność drzewa i wymagane pola zależne od typu pozycji. */
    private function validateItems(array $items): void
    {
        $errors = [];
        $previous = -1;

        foreach ($items as $i => $row) {
            if ((int) $row['depth'] > $previous + 1) {
                $errors["items.{$i}.depth"] = __('Pozycja jest zagnieżdżona zbyt głęboko.');
            }

            $previous = (int) $row['depth'];

            $missing = match ($row['type']) {
                'content' => empty($row['content_id']),
                'term' => empty($row['term_id']),
                'custom' => $this->renderer->safeUrl((string) ($row['url'] ?? '')) === null,
                default => ($row['label'] ?? '') === '',
            };

            if ($missing) {
                $errors["items.{$i}.".match ($row['type']) {
                    'content' => 'content_id', 'term' => 'term_id', 'custom' => 'url', default => 'label',
                }] = $row['type'] === 'custom' ? __('Podaj poprawny adres (https://, /ścieżka, #kotwica, mailto: lub tel:).') : __('To pole jest wymagane.');
            }

            if ($row['type'] === 'custom' && ($row['label'] ?? '') === '') {
                $errors["items.{$i}.label"] = __('To pole jest wymagane.');
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return list<array<string, mixed>> */
    private function flatItems(Menu $menu): array
    {
        $byParent = $menu->items()->get()->groupBy(fn (MenuItem $i) => $i->parent_id ?? 0);
        $flat = [];

        $walk = function (int $parent, int $depth) use (&$walk, &$flat, $byParent) {
            foreach ($byParent->get($parent, collect()) as $item) {
                $flat[] = [
                    'key' => 'i'.$item->id,
                    'depth' => $depth,
                    'type' => $item->type,
                    'label' => $item->label ?? '',
                    'content_id' => $item->content_id,
                    'term_id' => $item->term_id,
                    'url' => $item->url ?? '',
                    'new_tab' => $item->new_tab,
                    'mega' => $item->mega,
                    'mega_layout' => $item->mega_layout,
                    'mega_part_id' => $item->mega_part_id,
                ];
                $walk($item->id, $depth + 1);
            }
        };
        $walk(0, 0);

        return $flat;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'menu';
        $slug = $base;

        for ($i = 2; Menu::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
