<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Taxonomy\Term;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FieldGroupController extends Controller
{
    private const LOCATION_PARAMS = ['content_type', 'status', 'template', 'parent', 'term'];

    /** Ustawienia wspólne dla wszystkich typów pól (edytowane w części ogólnej formularza). */
    private const COMMON_SETTINGS = ['label', 'name', 'instructions', 'required', 'conditional_logic', 'wrapper', 'type'];

    public function __construct(private readonly FieldRegistry $registry) {}

    public function index(): Response
    {
        return Inertia::render('fields/index', [
            'groups' => FieldGroup::orderBy('menu_order')->orderBy('id')->get()->map(fn (FieldGroup $g) => [
                'id' => $g->id,
                'key' => $g->key,
                'title' => $g->title,
                'active' => $g->active,
                'fields_count' => count($g->fields ?? []),
                'location' => $this->describeLocation($g->location_rules ?? []),
            ]),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(FieldGroup $group): Response
    {
        return $this->form($group);
    }

    public function store(Request $request): RedirectResponse
    {
        $group = FieldGroup::create($this->validated($request, null) + ['menu_order' => (int) FieldGroup::max('menu_order') + 1]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grupa pól została zapisana.']);

        return to_route('cms.fields.edit', $group);
    }

    public function update(Request $request, FieldGroup $group): RedirectResponse
    {
        $group->update($this->validated($request, $group));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zapisano.']);

        return back();
    }

    public function destroy(FieldGroup $group): RedirectResponse
    {
        $group->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grupa pól została usunięta. Zapisane wartości w treściach zostają w bazie.']);

        return to_route('cms.fields.index');
    }

    public function toggle(FieldGroup $group): RedirectResponse
    {
        $group->update(['active' => ! $group->active]);

        return back();
    }

    public function duplicate(FieldGroup $group): RedirectResponse
    {
        $copy = $group->replicate();
        $copy->key = $this->uniqueKey($group->key);
        $copy->title = $group->title.' (kopia)';
        $copy->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zduplikowano grupę.']);

        return to_route('cms.fields.edit', $copy);
    }

    /** Eksport grupy w formacie JSON zgodnym z importerem. */
    public function export(FieldGroup $group): JsonResponse
    {
        return response()->json(['lumin' => 1, 'field_groups' => [$this->exportData($group)]], 200, [
            'Content-Disposition' => 'attachment; filename="'.$group->key.'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Import jednej lub wielu grup z JSON (ta sama walidacja co w formularzu). */
    public function import(Request $request): RedirectResponse
    {
        $payload = $request->validate(['json' => ['required', 'string', 'max:2000000']])['json'];
        $data = json_decode($payload, true);

        if (! is_array($data) || ! is_array($data['field_groups'] ?? null) || $data['field_groups'] === []) {
            throw ValidationException::withMessages(['json' => 'Nieprawidłowy plik: oczekiwano obiektu z listą „field_groups”.']);
        }

        $created = 0;

        foreach ($data['field_groups'] as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(['json' => 'Grupa nr '.($index + 1).' ma nieprawidłowy format.']);
            }

            try {
                $attributes = $this->validateData($item, null, enforceUniqueKey: false);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['json' => 'Grupa nr '.($index + 1).': '.collect($e->errors())->flatten()->first()]);
            }

            $attributes['key'] = $this->uniqueKey($attributes['key']);
            FieldGroup::create($attributes + ['menu_order' => (int) FieldGroup::max('menu_order') + 1]);
            $created++;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Zaimportowano grup: {$created}."]);

        return to_route('cms.fields.index');
    }

    private function form(?FieldGroup $group): Response
    {
        $types = collect($this->registry->all())->map(function ($type, $name) {
            $settings = $this->registry->resolveField(['type' => $name]);

            return [
                'name' => $name,
                'label' => $type->label(),
                'category' => $type->category(),
                'stores_data' => $type->storesData(),
                'settings' => (object) array_diff_key($settings, array_flip(self::COMMON_SETTINGS)),
            ];
        })->values();

        return Inertia::render('fields/edit', [
            'group' => $group ? [
                'id' => $group->id,
                'key' => $group->key,
                'title' => $group->title,
                'fields' => $group->fields ?? [],
                'location_rules' => $group->location_rules ?? [],
                'position' => $group->position ?: 'normal',
                'style' => $group->style ?: 'default',
                'label_placement' => $group->label_placement ?: 'top',
                'instruction_placement' => $group->instruction_placement ?: 'label',
                'active' => $group->active,
            ] : null,
            'fieldTypes' => $types,
            'contentTypes' => ContentType::orderBy('id')->get(['key', 'label_plural']),
            'terms' => Term::with('taxonomy:id,label')->orderBy('name')->get()->map(fn (Term $t) => ['id' => $t->id, 'name' => $t->name, 'taxonomy' => $t->taxonomy?->label]),
            'parents' => Content::whereHas('type', fn ($q) => $q->where('hierarchical', true))->orderBy('title')->get(['id', 'title']),
        ]);
    }

    private function validated(Request $request, ?FieldGroup $group): array
    {
        return $this->validateData($request->all(), $group);
    }

    private function validateData(array $input, ?FieldGroup $group, bool $enforceUniqueKey = true): array
    {
        $validator = validator($input, [
            'title' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/', ...($enforceUniqueKey ? [Rule::unique('field_groups', 'key')->ignore($group?->id)] : [])],
            'fields' => ['present', 'array', 'max:150'],
            'fields.*' => ['array'],
            'fields.*.type' => ['required', 'string', Rule::in(array_keys($this->registry->all()))],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.name' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'location_rules' => ['present', 'array', 'max:20'],
            'location_rules.*' => ['array', 'max:20'],
            'location_rules.*.*.param' => ['required', Rule::in(self::LOCATION_PARAMS)],
            'location_rules.*.*.operator' => ['required', Rule::in(['==', '!='])],
            'location_rules.*.*.value' => ['nullable', 'string', 'max:255'],
            'position' => ['required', Rule::in(['after_title', 'normal', 'side'])],
            'style' => ['required', Rule::in(['default', 'seamless'])],
            'label_placement' => ['required', Rule::in(['top', 'left'])],
            'instruction_placement' => ['required', Rule::in(['label', 'field'])],
            'active' => ['boolean'],
        ], [], ['title' => 'tytuł', 'fields.*.name' => 'nazwa pola', 'fields.*.type' => 'typ pola']);

        $validator->after(function ($v) use ($input) {
            $names = [];

            foreach ((array) ($input['fields'] ?? []) as $i => $field) {
                $type = $this->registry->has($field['type'] ?? '') ? $this->registry->get($field['type']) : null;
                $name = $field['name'] ?? '';

                if ($type?->storesData() && $name === '') {
                    $v->errors()->add("fields.{$i}.name", 'Podaj nazwę pola (małe litery, cyfry i podkreślenia).');
                }

                if ($name !== '') {
                    if (isset($names[$name])) {
                        $v->errors()->add("fields.{$i}.name", "Nazwa „{$name}” jest już użyta w tej grupie.");
                    }
                    $names[$name] = true;
                }
            }

            // Logika warunkowa może odwoływać się tylko do pól tej grupy.
            foreach ((array) ($input['fields'] ?? []) as $i => $field) {
                foreach ((array) ($field['conditional_logic'] ?? []) as $andGroup) {
                    foreach ((array) $andGroup as $rule) {
                        if (isset($rule['field']) && ! isset($names[$rule['field']])) {
                            $v->errors()->add("fields.{$i}.conditional_logic", 'Warunek odwołuje się do nieistniejącego pola.');
                        }
                    }
                }
            }
        });

        $data = $validator->validate();

        $key = $data['key'] ?? null;

        return [
            'key' => $group?->key ?? ($key ?: $this->uniqueKey('group_'.Str::slug($data['title'], '_'))),
            'title' => $data['title'],
            'fields' => array_map(fn ($f) => $this->cleanField($f), array_values($input['fields'])),
            'location_rules' => array_values(array_filter(array_map(
                fn ($rules) => array_values(array_map(fn ($r) => ['param' => $r['param'], 'operator' => $r['operator'], 'value' => (string) ($r['value'] ?? '')], $rules)),
                $data['location_rules'],
            ), fn ($rules) => $rules !== [])),
            'position' => $data['position'],
            'style' => $data['style'],
            'label_placement' => $data['label_placement'],
            'instruction_placement' => $data['instruction_placement'],
            'active' => (bool) ($data['active'] ?? true),
        ];
    }

    /** Usuwa klucze robocze z interfejsu (zaczynające się od "_") i puste reguły warunkowe. */
    private function cleanField(array $field): array
    {
        $field = array_filter($field, fn ($key) => ! str_starts_with((string) $key, '_'), ARRAY_FILTER_USE_KEY);

        if (empty($field['conditional_logic'])) {
            $field['conditional_logic'] = false;
        }

        return $field;
    }

    private function exportData(FieldGroup $group): array
    {
        return [
            'key' => $group->key,
            'title' => $group->title,
            'fields' => $group->fields ?? [],
            'location_rules' => $group->location_rules ?? [],
            'position' => $group->position ?: 'normal',
            'style' => $group->style ?: 'default',
            'label_placement' => $group->label_placement ?: 'top',
            'instruction_placement' => $group->instruction_placement ?: 'label',
            'active' => $group->active,
        ];
    }

    private function uniqueKey(string $base): string
    {
        $key = $base;
        $i = 2;

        while (FieldGroup::where('key', $key)->exists()) {
            $key = $base.'_'.$i++;
        }

        return $key;
    }

    /** Krótki opis reguł lokalizacji do listy grup. */
    private function describeLocation(array $groups): string
    {
        if ($groups === []) {
            return 'Brak reguł (grupa nigdzie się nie pokazuje)';
        }

        $labels = ['content_type' => 'Typ treści', 'status' => 'Status', 'template' => 'Szablon', 'parent' => 'Strona nadrzędna', 'term' => 'Termin'];

        return collect($groups)->map(fn ($rules) => collect($rules)->map(
            fn ($r) => ($labels[$r['param'] ?? ''] ?? $r['param'] ?? '?').' '.($r['operator'] === '!=' ? '≠' : '=').' '.($r['value'] ?? ''),
        )->implode(' i '))->implode(' lub ');
    }
}
