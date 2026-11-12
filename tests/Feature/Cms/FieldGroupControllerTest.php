<?php

namespace Tests\Feature\Cms;

use App\Cms\Fields\FieldGroup;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FieldGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
        $this->user = User::factory()->create();
    }

    private function payload(array $override = []): array
    {
        return $override + [
            'title' => 'Szczegóły oferty',
            'fields' => [
                ['type' => 'text', 'name' => 'podtytul', 'label' => 'Podtytuł', 'required' => true, '_id' => 'x1'],
                ['type' => 'true_false', 'name' => 'promocja', 'label' => 'Promocja'],
                ['type' => 'number', 'name' => 'cena_promo', 'label' => 'Cena', 'conditional_logic' => [[['field' => 'promocja', 'operator' => '==', 'value' => '1']]]],
            ],
            'location_rules' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
            'position' => 'normal', 'style' => 'default', 'label_placement' => 'top', 'instruction_placement' => 'label', 'active' => true,
        ];
    }

    public function test_requires_login(): void
    {
        $this->get('/cms/fields')->assertRedirect('/login');
    }

    public function test_store_creates_group_with_generated_key_and_strips_ui_keys(): void
    {
        $this->actingAs($this->user)->post('/cms/fields', $this->payload())->assertRedirect();

        $group = FieldGroup::firstOrFail();
        $this->assertSame('group_szczegoly_oferty', $group->key);
        $this->assertCount(3, $group->fields);
        $this->assertArrayNotHasKey('_id', $group->fields[0]);
        $this->assertFalse($group->fields[0]['conditional_logic']);
        $this->assertSame('promocja', $group->fields[2]['conditional_logic'][0][0]['field']);
    }

    public function test_created_group_is_used_by_content_form_and_validation(): void
    {
        $this->actingAs($this->user)->post('/cms/fields', $this->payload());

        $this->actingAs($this->user)->get('/cms/content/page/create')
            ->assertInertia(fn (Assert $p) => $p->has('groups', 1)->where('groups.0.fields.0.name', 'podtytul'));

        $this->actingAs($this->user)->post('/cms/content/page', ['title' => 'A', 'status' => 'draft', 'fields' => []])
            ->assertSessionHasErrors('fields.podtytul');
    }

    public function test_validation_rejects_bad_input(): void
    {
        $bad = $this->payload(['fields' => [
            ['type' => 'nie_ma', 'name' => 'a'],
            ['type' => 'text', 'name' => 'Zla Nazwa'],
            ['type' => 'text', 'name' => 'dup'],
            ['type' => 'text', 'name' => 'dup'],
            ['type' => 'text', 'name' => ''],
            ['type' => 'text', 'name' => 'x', 'conditional_logic' => [[['field' => 'brak', 'operator' => '==', 'value' => '1']]]],
        ], 'position' => 'nigdzie', 'location_rules' => [[['param' => 'hack', 'operator' => '~', 'value' => 'x']]]]);

        $this->actingAs($this->user)->post('/cms/fields', $bad)->assertSessionHasErrors([
            'fields.0.type', 'fields.1.name', 'fields.3.name', 'fields.4.name', 'fields.5.conditional_logic',
            'position', 'location_rules.0.0.param', 'location_rules.0.0.operator',
        ]);
        $this->assertDatabaseCount('field_groups', 0);
    }

    public function test_layout_fields_do_not_need_a_name(): void
    {
        $this->actingAs($this->user)->post('/cms/fields', $this->payload(['fields' => [['type' => 'tab', 'label' => 'Zakładka']]]))->assertSessionHasNoErrors();
    }

    public function test_update_keeps_key_and_changes_content(): void
    {
        $this->actingAs($this->user)->post('/cms/fields', $this->payload());
        $group = FieldGroup::firstOrFail();

        $this->actingAs($this->user)->put("/cms/fields/{$group->id}", $this->payload(['title' => 'Nowy tytuł', 'key' => 'inny_klucz', 'fields' => [['type' => 'text', 'name' => 'tylko']]]))->assertRedirect();

        $group->refresh();
        $this->assertSame(['group_szczegoly_oferty', 'Nowy tytuł', 1], [$group->key, $group->title, count($group->fields)]);
    }

    public function test_toggle_duplicate_and_delete(): void
    {
        $this->actingAs($this->user)->post('/cms/fields', $this->payload());
        $group = FieldGroup::firstOrFail();

        $this->actingAs($this->user)->post("/cms/fields/{$group->id}/toggle");
        $this->assertFalse($group->refresh()->active);

        $this->actingAs($this->user)->post("/cms/fields/{$group->id}/duplicate")->assertRedirect();
        $this->assertDatabaseHas('field_groups', ['key' => 'group_szczegoly_oferty_2', 'title' => 'Szczegóły oferty (kopia)']);

        $this->actingAs($this->user)->delete("/cms/fields/{$group->id}")->assertRedirect('/cms/fields');
        $this->assertDatabaseCount('field_groups', 1);
    }

    public function test_index_describes_locations(): void
    {
        $this->actingAs($this->user)->post('/cms/fields', $this->payload());

        $this->actingAs($this->user)->get('/cms/fields')->assertInertia(fn (Assert $p) => $p->component('fields/index')
            ->where('groups.0.fields_count', 3)->where('groups.0.location', 'Typ treści = page'));
    }

    public function test_edit_page_exposes_all_field_types_with_default_settings(): void
    {
        $this->actingAs($this->user)->get('/cms/fields/create')->assertInertia(fn (Assert $p) => $p->component('fields/edit')
            ->has('fieldTypes', 33)->where('group', null)->has('contentTypes', 2));
    }

    public function test_export_and_import_roundtrip(): void
    {
        $this->actingAs($this->user)->post('/cms/fields', $this->payload());
        $group = FieldGroup::firstOrFail();

        $json = $this->actingAs($this->user)->get("/cms/fields/{$group->id}/export")->assertOk()->getContent();
        $this->assertSame(1, json_decode($json, true)['lumin']);

        $this->actingAs($this->user)->post('/cms/fields/import', ['json' => $json])->assertRedirect('/cms/fields');

        $this->assertDatabaseCount('field_groups', 2);
        $this->assertSame(FieldGroup::find($group->id)->fields, FieldGroup::where('key', 'group_szczegoly_oferty_2')->first()->fields);
    }

    public function test_import_rejects_invalid_files(): void
    {
        $this->actingAs($this->user)->post('/cms/fields/import', ['json' => 'to nie json'])->assertSessionHasErrors('json');
        $this->actingAs($this->user)->post('/cms/fields/import', ['json' => '{"field_groups":[{"title":"X","fields":[{"type":"nie_ma"}],"location_rules":[],"position":"normal","style":"default","label_placement":"top","instruction_placement":"label"}]}'])->assertSessionHasErrors('json');
        $this->assertDatabaseCount('field_groups', 0);
    }
}
