<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Roles\RoleRegistry;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContainerFieldsTest extends TestCase
{
    use RefreshDatabase;

    private FieldValidator $validator;

    private FieldRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
        $this->validator = app(FieldValidator::class);
        $this->registry = app(FieldRegistry::class);
    }

    private function errors(array $fields, array $data): ?array
    {
        try {
            $this->validator->validate($fields, $data);

            return null;
        } catch (ValidationException $e) {
            return $e->errors();
        }
    }

    private function group(string $key, array $fields, array $rules = []): FieldGroup
    {
        return FieldGroup::create([
            'key' => $key, 'title' => $key, 'fields' => $fields,
            'location_rules' => $rules ?: [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
            'position' => 'normal', 'style' => 'default', 'label_placement' => 'top', 'instruction_placement' => 'label', 'active' => true,
        ]);
    }

    private function address(): array
    {
        return [[
            'type' => 'group', 'name' => 'adres', 'label' => 'Adres',
            'sub_fields' => [
                ['type' => 'text', 'name' => 'ulica', 'label' => 'Ulica', 'required' => true],
                ['type' => 'text', 'name' => 'miasto', 'label' => 'Miasto'],
                ['type' => 'text', 'name' => 'kod', 'conditional_logic' => [[['field' => 'miasto', 'operator' => '!=empty']]]],
            ],
        ]];
    }

    private function flexible(array $override = []): array
    {
        return [$override + [
            'type' => 'flexible_content', 'name' => 'sekcje', 'label' => 'Sekcje',
            'layouts' => [
                ['name' => 'tekst', 'label' => 'Tekst', 'max' => 2, 'sub_fields' => [['type' => 'text', 'name' => 'naglowek', 'required' => true]]],
                ['name' => 'obraz', 'label' => 'Obraz', 'min' => 0, 'sub_fields' => [['type' => 'url', 'name' => 'adres'], ['type' => 'text', 'name' => 'opis']]],
            ],
        ]];
    }

    // ---------------------------------------------------------------- Group

    public function test_group_validates_nested_required_even_without_input_and_normalizes(): void
    {
        $this->assertArrayHasKey('adres.ulica', $this->errors($this->address(), []));
        $this->assertArrayHasKey('adres.ulica', $this->errors($this->address(), ['adres' => ['miasto' => 'X']]));

        $values = $this->validator->validate($this->address(), ['adres' => ['ulica' => 'Długa 1', 'miasto' => '', 'kod' => '50-001', 'smiec' => 'x']]);

        // Pole "kod" zależy od miasta (puste), więc jest odrzucone; obce klucze znikają.
        $this->assertSame(['ulica' => 'Długa 1', 'miasto' => null], array_intersect_key($values['adres'], array_flip(['ulica', 'miasto'])));
        $this->assertArrayNotHasKey('kod', $values['adres']);
        $this->assertArrayNotHasKey('smiec', $values['adres']);
    }

    public function test_optional_empty_group_is_stored_as_null_and_formats_to_all_keys(): void
    {
        $fields = [['type' => 'group', 'name' => 'adres', 'sub_fields' => [['type' => 'text', 'name' => 'ulica']]]];

        $this->assertNull($this->validator->validate($fields, ['adres' => ['ulica' => '']])['adres']);

        $field = $this->registry->resolveField($fields[0]);
        $this->assertSame(['ulica' => 'X'], $this->registry->get('group')->format(['ulica' => 'X'], $field));
        $this->assertSame(['ulica' => null], $this->registry->get('group')->format(null, $field));
    }

    // ------------------------------------------------------------- Flexible

    public function test_flexible_rows_use_their_layout_fields(): void
    {
        $values = $this->validator->validate($this->flexible(), ['sekcje' => [
            ['_layout' => 'tekst', 'naglowek' => 'Cześć', 'opis' => 'nie ten układ'],
            ['_layout' => 'obraz', 'adres' => 'https://example.com/a.png', 'opis' => 'Opis'],
        ]]);

        $this->assertSame(['_layout' => 'tekst', 'naglowek' => 'Cześć'], $values['sekcje'][0]);
        $this->assertSame(['_layout' => 'obraz', 'adres' => 'https://example.com/a.png', 'opis' => 'Opis'], $values['sekcje'][1]);

        $this->assertArrayHasKey('sekcje.0.naglowek', $this->errors($this->flexible(), ['sekcje' => [['_layout' => 'tekst', 'naglowek' => '']]]));
        $this->assertArrayHasKey('sekcje.1.adres', $this->errors($this->flexible(), ['sekcje' => [['_layout' => 'tekst', 'naglowek' => 'a'], ['_layout' => 'obraz', 'adres' => 'nie url']]]));
        $this->assertNull($this->validator->validate($this->flexible(), ['sekcje' => []])['sekcje']);
    }

    public function test_flexible_rejects_unknown_layouts_and_enforces_limits(): void
    {
        $row = ['_layout' => 'tekst', 'naglowek' => 'a'];

        $this->assertArrayHasKey('sekcje', $this->errors($this->flexible(), ['sekcje' => [['_layout' => 'hack']]]));
        $this->assertArrayHasKey('sekcje', $this->errors($this->flexible(), ['sekcje' => [['naglowek' => 'a']]]));
        $this->assertArrayHasKey('sekcje', $this->errors($this->flexible(), ['sekcje' => [$row, $row, $row]]));   // max 2 dla układu
        $this->assertArrayHasKey('sekcje', $this->errors($this->flexible(['max' => 1]), ['sekcje' => [$row, $row]]));
        $this->assertArrayHasKey('sekcje', $this->errors($this->flexible(['min' => 2]), ['sekcje' => [$row]]));
        $this->assertNull($this->errors($this->flexible(), ['sekcje' => [$row, $row]]));
    }

    public function test_flexible_format_keeps_layout_key_and_skips_unknown_rows(): void
    {
        $field = $this->registry->resolveField($this->flexible()[0]);
        $out = $this->registry->get('flexible_content')->format([
            ['_layout' => 'tekst', 'naglowek' => 'A'], ['_layout' => 'usuniety', 'x' => 1], ['_layout' => 'obraz', 'adres' => 'https://e.com'],
        ], $field);

        $this->assertCount(2, $out);
        $this->assertSame(['_layout' => 'tekst', 'naglowek' => 'A'], $out[0]);
        $this->assertSame('obraz', $out[1]['_layout']);
        $this->assertSame([], $this->registry->get('flexible_content')->format(null, $field));
    }

    // ---------------------------------------------------------------- Clone

    public function test_seamless_clone_inserts_source_fields_with_optional_prefixes_and_rewrites_conditions(): void
    {
        $this->group('group_seo', [
            ['type' => 'text', 'name' => 'tytul', 'label' => 'Tytuł', 'required' => true],
            ['type' => 'text', 'name' => 'opis', 'label' => 'Opis', 'conditional_logic' => [[['field' => 'tytul', 'operator' => '!=empty']]]],
        ]);

        $plain = $this->registry->expand([['type' => 'clone', 'clone' => ['group_seo']]]);
        $this->assertSame(['tytul', 'opis'], array_column($plain, 'name'));

        $prefixed = $this->registry->expand([['type' => 'clone', 'name' => 'meta', 'label' => 'Meta', 'prefix_name' => true, 'prefix_label' => true, 'clone' => ['group_seo']]]);
        $this->assertSame(['meta_tytul', 'meta_opis'], array_column($prefixed, 'name'));
        $this->assertSame(['Meta Tytuł', 'Meta Opis'], array_column($prefixed, 'label'));
        $this->assertSame('meta_tytul', $prefixed[1]['conditional_logic'][0][0]['field']);

        // Działa w walidatorze: pola klonu są zwykłymi polami.
        $fields = [['type' => 'clone', 'name' => 'meta', 'prefix_name' => true, 'clone' => ['group_seo']]];
        $this->assertArrayHasKey('meta_tytul', $this->errors($fields, []));
        $this->assertSame(['meta_tytul' => 'T', 'meta_opis' => null], array_intersect_key($this->validator->validate($fields, ['meta_tytul' => 'T']), array_flip(['meta_tytul', 'meta_opis'])));
    }

    public function test_clone_of_single_field_and_group_display(): void
    {
        $this->group('group_seo', [['type' => 'text', 'name' => 'tytul'], ['type' => 'text', 'name' => 'opis']]);

        $one = $this->registry->expand([['type' => 'clone', 'clone' => ['group_seo.opis']]]);
        $this->assertSame(['opis'], array_column($one, 'name'));

        $grouped = $this->registry->expand([['type' => 'clone', 'name' => 'meta', 'label' => 'Meta', 'display' => 'group', 'clone' => ['group_seo']]]);
        $this->assertCount(1, $grouped);
        $this->assertSame('group', $grouped[0]['type']);
        $this->assertSame(['tytul', 'opis'], array_column($grouped[0]['sub_fields'], 'name'));

        $values = $this->validator->validate([['type' => 'clone', 'name' => 'meta', 'display' => 'group', 'clone' => ['group_seo']]], ['meta' => ['tytul' => 'A']]);
        $this->assertSame('A', $values['meta']['tytul']);
    }

    public function test_clone_works_inside_containers_and_ignores_missing_sources(): void
    {
        $this->group('group_seo', [['type' => 'text', 'name' => 'tytul', 'required' => true]]);

        $fields = [[
            'type' => 'repeater', 'name' => 'wiersze',
            'sub_fields' => [['type' => 'clone', 'clone' => ['group_seo', 'nie_ma', 'nie_ma.pole']]],
        ]];

        $this->assertArrayHasKey('wiersze.0.tytul', $this->errors($fields, ['wiersze' => [['tytul' => '']]]));
        $this->assertSame([['tytul' => 'ok']], $this->validator->validate($fields, ['wiersze' => [['tytul' => 'ok']]])['wiersze']);

        $layoutFields = [[
            'type' => 'flexible_content', 'name' => 'f',
            'layouts' => [['name' => 'a', 'label' => 'A', 'sub_fields' => [['type' => 'clone', 'clone' => ['group_seo']]]]],
        ]];
        $this->assertArrayHasKey('f.0.tytul', $this->errors($layoutFields, ['f' => [['_layout' => 'a']]]));
    }

    public function test_clone_cycles_terminate(): void
    {
        $this->group('group_a', [['type' => 'text', 'name' => 'a'], ['type' => 'clone', 'clone' => ['group_b']]]);
        $this->group('group_b', [['type' => 'text', 'name' => 'b'], ['type' => 'clone', 'clone' => ['group_a']]]);

        $names = array_column($this->registry->expand([['type' => 'clone', 'clone' => ['group_a']]]), 'name');

        $this->assertSame(['a', 'b'], $names);
    }

    // -------------------------------------------------- zapis grup i treści

    private function admin(): User
    {
        return User::factory()->role(RoleRegistry::ADMINISTRATOR)->create();
    }

    private function groupPayload(array $fields, string $key = 'g_test'): array
    {
        return [
            'title' => 'Test', 'key' => $key, 'fields' => $fields,
            'location_rules' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
            'position' => 'normal', 'style' => 'default', 'label_placement' => 'top', 'instruction_placement' => 'label', 'active' => true,
        ];
    }

    public function test_group_editor_saves_layouts_and_strips_ui_keys(): void
    {
        $this->actingAs($this->admin());

        $this->post('/cms/fields', $this->groupPayload([[
            '_id' => 'x', 'type' => 'flexible_content', 'name' => 'sekcje', 'label' => 'Sekcje',
            'layouts' => [['_id' => 'l1', 'name' => 'tekst', 'label' => 'Tekst', 'min' => '', 'max' => '', 'sub_fields' => [['_id' => 's', 'type' => 'text', 'name' => 'naglowek', 'label' => 'N']]]],
        ]]))->assertRedirect()->assertSessionHasNoErrors();

        $saved = FieldGroup::where('key', 'g_test')->first()->fields[0];
        $this->assertArrayNotHasKey('_id', $saved);
        $this->assertArrayNotHasKey('_id', $saved['layouts'][0]);
        $this->assertArrayNotHasKey('_id', $saved['layouts'][0]['sub_fields'][0]);
        $this->assertSame('naglowek', $saved['layouts'][0]['sub_fields'][0]['name']);
    }

    public function test_group_editor_validates_layouts_groups_and_clones(): void
    {
        $this->actingAs($this->admin());
        $flex = fn (array $layouts) => [['type' => 'flexible_content', 'name' => 'f', 'layouts' => $layouts]];

        $this->post('/cms/fields', $this->groupPayload($flex([['name' => 'Zła Nazwa', 'label' => 'x', 'sub_fields' => []]])))->assertSessionHasErrors('fields.0.layouts.0.name');
        $this->post('/cms/fields', $this->groupPayload($flex([['name' => 'a', 'sub_fields' => []], ['name' => 'a', 'sub_fields' => []]])))->assertSessionHasErrors('fields.0.layouts.1.name');
        $this->post('/cms/fields', $this->groupPayload($flex([['name' => 'a', 'sub_fields' => [['type' => 'text', 'name' => '']]]])))->assertSessionHasErrors('fields.0.layouts.0.sub_fields.0.name');
        $this->post('/cms/fields', $this->groupPayload($flex([['name' => 'a', 'sub_fields' => [['type' => 'text', 'name' => 'x'], ['type' => 'text', 'name' => 'x']]]])))->assertSessionHasErrors('fields.0.layouts.0.sub_fields.1.name');

        // Group wymaga unikalnych nazw pól podrzędnych.
        $this->post('/cms/fields', $this->groupPayload([['type' => 'group', 'name' => 'g', 'sub_fields' => [['type' => 'text', 'name' => 'x'], ['type' => 'text', 'name' => 'x']]]]))->assertSessionHasErrors('fields.0.sub_fields.1.name');

        // Clone: poprawny format odwołań, bez klonowania samej siebie, nazwa tylko gdy potrzebna.
        $this->post('/cms/fields', $this->groupPayload([['type' => 'clone', 'clone' => ['group_x']]]))->assertSessionHasNoErrors();
        $this->post('/cms/fields', $this->groupPayload([['type' => 'clone', 'clone' => ['Zły Klucz']]], 'g_other'))->assertSessionHasErrors('fields.0.clone');
        $this->post('/cms/fields', $this->groupPayload([['type' => 'clone', 'clone' => ['g_self']]], 'g_self'))->assertSessionHasErrors('fields.0.clone');
        $this->post('/cms/fields', $this->groupPayload([['type' => 'clone', 'display' => 'group', 'clone' => ['group_x']]], 'g_named'))->assertSessionHasErrors('fields.0.name');
    }

    public function test_edit_page_lists_clone_sources_without_the_edited_group(): void
    {
        $this->group('group_a', [['type' => 'text', 'name' => 'a', 'label' => 'Alfa'], ['type' => 'message', 'message' => 'x']]);
        $b = $this->group('group_b', []);
        $this->actingAs($this->admin());

        $this->get("/cms/fields/{$b->id}/edit")->assertInertia(fn ($p) => $p->component('fields/edit')
            ->has('cloneSources', 1)->where('cloneSources.0.key', 'group_a')->where('cloneSources.0.fields', [['name' => 'a', 'label' => 'Alfa']]));
    }

    public function test_content_roundtrip_for_group_flexible_and_clone_with_content_form(): void
    {
        $this->group('group_seo', [['type' => 'text', 'name' => 'tytul', 'label' => 'Tytuł SEO']], [[['param' => 'content_type', 'operator' => '==', 'value' => 'post']]]);
        $this->group('g_all', [
            ...$this->address(), ...$this->flexible(),
            ['type' => 'clone', 'name' => 'meta', 'display' => 'group', 'clone' => ['group_seo']],
        ]);
        $user = $this->admin();

        $this->actingAs($user)->post('/cms/content/page', [
            'title' => 'Strona', 'status' => 'draft',
            'fields' => [
                'adres' => ['ulica' => 'Długa 1'],
                'sekcje' => [['_layout' => 'tekst', 'naglowek' => 'Witaj']],
                'meta' => ['tytul' => 'SEO'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $content = Content::where('title', 'Strona')->firstOrFail();
        $this->assertSame('Długa 1', $content->field('adres')['ulica']);
        $this->assertSame('Witaj', $content->field('sekcje')[0]['naglowek']);
        $this->assertSame('tekst', $content->field('sekcje')[0]['_layout']);
        $this->assertSame('SEO', $content->field('meta')['tytul']);

        $this->actingAs($user)->post('/cms/content/page', [
            'title' => 'Zła', 'status' => 'draft', 'fields' => ['sekcje' => [['_layout' => 'tekst', 'naglowek' => '']]],
        ])->assertSessionHasErrors(['fields.adres.ulica', 'fields.sekcje.0.naglowek']);

        // Formularz dostaje rozwinięte pola (klon jako grupa).
        $this->actingAs($user)->get("/cms/content/page/{$content->id}/edit")->assertInertia(fn ($p) => $p
            ->where('groups.0.fields.2.type', 'group')->where('groups.0.fields.2.sub_fields.0.name', 'tytul'));
    }

    public function test_relation_lookup_works_through_group_and_flexible_layout_paths(): void
    {
        $page = Content::create(['content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Alfa', 'slug' => 'alfa', 'status' => 'published', 'published_at' => now()]);
        $group = $this->group('g_rel', [
            ['type' => 'group', 'name' => 'blok', 'sub_fields' => [['type' => 'post_object', 'name' => 'cel', 'post_type' => ['page']]]],
            ['type' => 'flexible_content', 'name' => 'sekcje', 'layouts' => [['name' => 'link', 'label' => 'L', 'sub_fields' => [['type' => 'post_object', 'name' => 'cel', 'post_type' => ['page']]]]]],
        ]);
        $this->actingAs($this->admin());

        foreach (['blok.cel', 'sekcje.link.cel'] as $path) {
            $this->getJson("/cms/relations/{$group->id}?field={$path}&q=alfa")->assertOk()->assertJsonPath('items.0.id', $page->id);
        }

        $this->getJson("/cms/relations/{$group->id}?field=sekcje.cel&q=alfa")->assertNotFound();
    }
}
