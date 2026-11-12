<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Fields\FieldRegistry;
use App\Cms\Fields\FieldValidator;
use App\Cms\Support\HtmlSanitizer;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RichFieldsTest extends TestCase
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

    private function page(string $title, string $status = 'published'): Content
    {
        return Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'),
            'title' => $title, 'slug' => str($title)->slug(), 'status' => $status, 'published_at' => now(),
        ]);
    }

    public function test_sanitizer_strips_dangerous_markup(): void
    {
        $clean = app(HtmlSanitizer::class)->clean(
            '<p onclick="x()" style="color:red">Cześć <script>alert(1)</script><a href="javascript:alert(1)">zły</a> '
            .'<a href="https://example.com" target="_blank">ok</a><img src="data:text/html,x" alt="a"><iframe src="//e"></iframe><font>tekst</font></p>'
        );

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringNotContainsString('data:', $clean);
        $this->assertStringNotContainsString('<font', $clean);
        $this->assertStringContainsString('tekst', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
        $this->assertStringContainsString('Cześć', $clean);
    }

    public function test_wysiwyg_normalizes_and_treats_empty_paragraph_as_empty(): void
    {
        $fields = [['type' => 'wysiwyg', 'name' => 'opis']];

        $this->assertNull($this->validator->validate($fields, ['opis' => '<p></p>'])['opis']);
        $this->assertSame('<p>Tekst</p>', $this->validator->validate($fields, ['opis' => '<p>Tekst</p><script>x</script>'])['opis']);
        $this->assertArrayHasKey('opis', $this->errors([['type' => 'wysiwyg', 'name' => 'opis', 'required' => true]], ['opis' => '']));
    }

    public function test_oembed_accepts_only_known_providers_and_builds_iframe(): void
    {
        $fields = [['type' => 'oembed', 'name' => 'film']];

        $this->assertArrayHasKey('film', $this->errors($fields, ['film' => 'https://evil.example/watch?v=dQw4w9WgXcQ']));
        $this->assertArrayHasKey('film', $this->errors($fields, ['film' => 'javascript:alert(1)']));
        $this->assertNull($this->errors($fields, ['film' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']));
        $this->assertNull($this->errors($fields, ['film' => 'https://vimeo.com/123456']));

        $field = $this->registry->resolveField($fields[0]);
        $html = (string) $this->registry->get('oembed')->format('https://youtu.be/dQw4w9WgXcQ', $field);
        $this->assertStringContainsString('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', $html);
    }

    public function test_post_object_validates_type_and_status_and_formats(): void
    {
        $page = $this->page('Strona A');
        $draft = $this->page('Szkic', 'draft');
        $fields = [['type' => 'post_object', 'name' => 'strona', 'post_type' => ['page'], 'post_status' => ['published']]];

        $this->assertArrayHasKey('strona', $this->errors($fields, ['strona' => $draft->id]));
        $this->assertArrayHasKey('strona', $this->errors($fields, ['strona' => 9999]));
        $this->assertSame($page->id, $this->validator->validate($fields, ['strona' => (string) $page->id])['strona']);

        $field = $this->registry->resolveField($fields[0]);
        $this->assertSame($page->id, $this->registry->get('post_object')->format($page->id, $field)->id);
        $this->assertSame($page->id, $this->registry->get('post_object')->format($page->id, [...$field, 'return_format' => 'id']));
    }

    public function test_relationship_keeps_order_dedups_and_respects_max(): void
    {
        $a = $this->page('A');
        $b = $this->page('B');
        $c = $this->page('C');
        $fields = [['type' => 'relationship', 'name' => 'powiazane', 'max' => 2]];

        $this->assertArrayHasKey('powiazane', $this->errors($fields, ['powiazane' => [$a->id, $b->id, $c->id]]));
        $this->assertSame([$b->id, $a->id], $this->validator->validate($fields, ['powiazane' => [$b->id, $a->id]])['powiazane']);
        $this->assertSame([$b->id, $a->id], $this->validator->validate([['type' => 'relationship', 'name' => 'powiazane']], ['powiazane' => [$b->id, $a->id, $b->id]])['powiazane']);

        $field = $this->registry->resolveField($fields[0]);
        $out = $this->registry->get('relationship')->format([$b->id, $a->id], $field);
        $this->assertSame(['B', 'A'], array_map(fn ($p) => $p->title, $out));
    }

    public function test_taxonomy_field_limits_to_taxonomy(): void
    {
        $cat = Term::create(['taxonomy_id' => Taxonomy::where('key', 'category')->value('id'), 'name' => 'Newsy', 'slug' => 'newsy']);
        $tag = Term::create(['taxonomy_id' => Taxonomy::where('key', 'tag')->value('id'), 'name' => 'Tag', 'slug' => 'tag']);
        $fields = [['type' => 'taxonomy', 'name' => 'kat', 'taxonomy' => 'category']];

        $this->assertArrayHasKey('kat.0', $this->errors($fields, ['kat' => [$tag->id]]));
        $this->assertSame([$cat->id], $this->validator->validate($fields, ['kat' => [$cat->id]])['kat']);

        $single = [['type' => 'taxonomy', 'name' => 'kat', 'taxonomy' => 'category', 'field_type' => 'select']];
        $this->assertSame($cat->id, $this->validator->validate($single, ['kat' => $cat->id])['kat']);
    }

    public function test_user_field_never_exposes_email(): void
    {
        $user = User::factory()->create();
        $fields = [['type' => 'user', 'name' => 'autor']];
        $field = $this->registry->resolveField($fields[0]);

        $this->assertSame($user->id, $this->validator->validate($fields, ['autor' => $user->id])['autor']);
        $this->assertSame(['id' => $user->id, 'name' => $user->name], $this->registry->get('user')->format($user->id, $field));
    }

    private function repeater(array $override = []): array
    {
        return [[
            'type' => 'repeater', 'name' => 'slajdy', 'label' => 'Slajdy', 'max' => 3,
            'sub_fields' => [
                ['type' => 'text', 'name' => 'tytul', 'label' => 'Tytuł', 'required' => true],
                ['type' => 'true_false', 'name' => 'link', 'label' => 'Z linkiem'],
                ['type' => 'url', 'name' => 'adres', 'label' => 'Adres', 'required' => true, 'conditional_logic' => [[['field' => 'link', 'operator' => '==', 'value' => '1']]]],
            ],
            ...$override,
        ]];
    }

    public function test_repeater_validates_each_row_with_row_scoped_conditional_logic(): void
    {
        $errors = $this->errors($this->repeater(), ['slajdy' => [
            ['tytul' => 'Ok', 'link' => '0'],
            ['tytul' => '', 'link' => '1', 'adres' => ''],
        ]]);

        // Wiersz 0: adres ukryty, więc nie jest wymagany. Wiersz 1: oba błędy.
        $this->assertArrayNotHasKey('slajdy.0.adres', $errors);
        $this->assertArrayHasKey('slajdy.1.tytul', $errors);
        $this->assertArrayHasKey('slajdy.1.adres', $errors);
    }

    public function test_repeater_normalizes_rows_and_drops_hidden_values(): void
    {
        $values = $this->validator->validate($this->repeater(), ['slajdy' => [
            5 => ['tytul' => 'Pierwszy', 'link' => '0', 'adres' => 'https://ukryty.example'],
            9 => ['tytul' => 'Drugi', 'link' => '1', 'adres' => 'https://example.com'],
        ]]);

        $this->assertSame([
            ['tytul' => 'Pierwszy', 'link' => false],
            ['tytul' => 'Drugi', 'link' => true, 'adres' => 'https://example.com'],
        ], $values['slajdy']);
    }

    public function test_repeater_enforces_row_limits_and_ignores_non_array_rows(): void
    {
        $row = ['tytul' => 'x', 'link' => '0'];

        $this->assertArrayHasKey('slajdy', $this->errors($this->repeater(), ['slajdy' => [$row, $row, $row, $row]]));
        $this->assertArrayHasKey('slajdy', $this->errors($this->repeater(['min' => 2]), ['slajdy' => [$row]]));
        $this->assertArrayHasKey('slajdy.0', $this->errors($this->repeater(), ['slajdy' => ['tekst']]));
        $this->assertNull($this->validator->validate($this->repeater(), ['slajdy' => []])['slajdy']);
    }

    public function test_repeater_format_formats_sub_fields_and_nested_repeaters_work(): void
    {
        $fields = [[
            'type' => 'repeater', 'name' => 'sekcje',
            'sub_fields' => [
                ['type' => 'wysiwyg', 'name' => 'tresc'],
                ['type' => 'repeater', 'name' => 'punkty', 'sub_fields' => [['type' => 'text', 'name' => 'tekst', 'required' => true]]],
            ],
        ]];

        $errors = $this->errors($fields, ['sekcje' => [['tresc' => '<p>A</p>', 'punkty' => [['tekst' => ''], ['tekst' => 'ok']]]]]);
        $this->assertArrayHasKey('sekcje.0.punkty.0.tekst', $errors);

        $stored = $this->validator->validate($fields, ['sekcje' => [['tresc' => '<p>A</p>', 'punkty' => [['tekst' => 'jeden']]]]]);
        $this->assertSame('jeden', $stored['sekcje'][0]['punkty'][0]['tekst']);

        $field = $this->registry->resolveField($fields[0]);
        $out = $this->registry->get('repeater')->format($stored['sekcje'], $field);
        $this->assertSame('<p>A</p>', (string) $out[0]['tresc']);
        $this->assertSame('jeden', $out[0]['punkty'][0]['tekst']);
    }

    public function test_repeater_roundtrip_through_content_api_and_value_for(): void
    {
        $user = User::factory()->create();
        FieldGroup::create([
            'key' => 'g', 'title' => 'G', 'fields' => $this->repeater(),
            'location_rules' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'page']]],
            'position' => 'normal', 'style' => 'default', 'label_placement' => 'top', 'instruction_placement' => 'label', 'active' => true,
        ]);

        $this->actingAs($user)->post('/cms/content/page', [
            'title' => 'Z repeaterem', 'status' => 'draft',
            'fields' => ['slajdy' => [['tytul' => 'Pierwszy', 'link' => '0']]],
        ])->assertRedirect();

        $content = Content::where('title', 'Z repeaterem')->firstOrFail();
        $this->assertSame('Pierwszy', $content->field('slajdy')[0]['tytul']);

        $this->actingAs($user)->post('/cms/content/page', [
            'title' => 'Zły', 'status' => 'draft', 'fields' => ['slajdy' => [['tytul' => '']]],
        ])->assertSessionHasErrors('fields.slajdy.0.tytul');
    }

    public function test_relation_endpoint_searches_and_resolves_labels_by_stored_definition(): void
    {
        $user = User::factory()->create();
        $a = $this->page('Alfa');
        $this->page('Beta');
        $this->page('Szkic alfa', 'draft');

        $group = FieldGroup::create([
            'key' => 'r', 'title' => 'R',
            'fields' => [['type' => 'repeater', 'name' => 'blok', 'sub_fields' => [['type' => 'post_object', 'name' => 'cel', 'post_type' => ['page']]]]],
            'location_rules' => [], 'position' => 'normal', 'style' => 'default', 'label_placement' => 'top', 'instruction_placement' => 'label', 'active' => true,
        ]);

        $this->actingAs($user)->getJson("/cms/relations/{$group->id}?field=blok.cel&q=alfa")
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.label', 'Alfa');

        $this->actingAs($user)->getJson("/cms/relations/{$group->id}?field=blok.cel&ids={$a->id}")
            ->assertOk()->assertJsonPath('items.0.id', $a->id);

        $this->actingAs($user)->getJson("/cms/relations/{$group->id}?field=blok")->assertNotFound();
        $this->actingAs($user)->getJson("/cms/relations/{$group->id}?field=nie.ma")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->getJson("/cms/relations/{$group->id}?field=blok.cel")->assertUnauthorized();
    }

    public function test_group_builder_validates_sub_fields(): void
    {
        $user = User::factory()->create();
        $base = [
            'title' => 'Slajder', 'location_rules' => [], 'position' => 'normal', 'style' => 'default',
            'label_placement' => 'top', 'instruction_placement' => 'label', 'active' => true,
        ];

        $dup = $base + ['fields' => [['type' => 'repeater', 'name' => 'slajdy', 'sub_fields' => [
            ['type' => 'text', 'name' => 'a'], ['type' => 'text', 'name' => 'a'],
        ]]]];
        $this->actingAs($user)->post('/cms/fields', $dup)->assertSessionHasErrors('fields.0.sub_fields.1.name');

        $bad = $base + ['fields' => [['type' => 'repeater', 'name' => 'slajdy', 'sub_fields' => [['type' => 'nieznany', 'name' => 'a']]]]];
        $this->actingAs($user)->post('/cms/fields', $bad)->assertSessionHasErrors('fields.0.sub_fields.0.type');

        $ok = $base + ['fields' => [['type' => 'repeater', 'name' => 'slajdy', 'sub_fields' => [['type' => 'text', 'name' => 'a', '_id' => 'tmp']]]]];
        $this->actingAs($user)->post('/cms/fields', $ok)->assertRedirect();
        $this->assertArrayNotHasKey('_id', FieldGroup::firstOrFail()->fields[0]['sub_fields'][0]);
    }
}
