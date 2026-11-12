<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Fields\FieldGroup;
use App\Cms\Importer\Exporter;
use App\Cms\Importer\Importer;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->role(RoleRegistry::ADMINISTRATOR)->create();
    }

    private function imp(array $data, bool $dry = false, string $existing = 'skip'): array
    {
        return app(Importer::class)->run($data, $dry, $existing, null)->toArray();
    }

    private function sample(): array
    {
        return [
            'lumin' => 1,
            'content_types' => [[
                'key' => 'event', 'label' => 'Wydarzenie', 'label_plural' => 'Wydarzenia', 'icon' => 'calendar',
                'hierarchical' => true, 'supports' => ['title', 'editor', 'seo'], 'url_prefix' => 'wydarzenia',
            ]],
            'taxonomies' => [[
                'key' => 'city', 'label' => 'Miasto', 'label_plural' => 'Miasta', 'hierarchical' => true, 'content_types' => ['event'],
                'terms' => [
                    ['name' => 'Wrocław', 'slug' => 'wroclaw', 'parent' => 'polska'],
                    ['name' => 'Polska', 'slug' => 'polska'],
                ],
            ]],
            'field_groups' => [[
                'key' => 'group_event', 'title' => 'Wydarzenie',
                'fields' => [['type' => 'text', 'name' => 'place', 'label' => 'Miejsce']],
                'location_rules' => [[['param' => 'content_type', 'operator' => '==', 'value' => 'event']]],
                'position' => 'normal', 'style' => 'default', 'label_placement' => 'top', 'instruction_placement' => 'label',
            ]],
            'contents' => [
                ['type' => 'event', 'title' => 'Dziecko', 'slug' => 'dziecko', 'parent' => 'rodzic', 'status' => 'published',
                    'fields' => ['place' => 'Rynek'], 'terms' => ['city' => ['wroclaw']], 'seo' => ['title' => 'SEO', 'noindex' => true, 'evil' => 'x']],
                ['type' => 'event', 'title' => 'Rodzic', 'slug' => 'rodzic'],
            ],
        ];
    }

    public function test_full_import_creates_everything_with_relations(): void
    {
        $report = $this->imp($this->sample());

        $this->assertSame([], $report['errors']);
        $this->assertTrue($report['applied']);
        $this->assertSame(1, $report['sections']['content_types']['created']);
        $this->assertSame(2, $report['sections']['terms']['created']);
        $this->assertSame(2, $report['sections']['contents']['created']);

        $type = ContentType::where('key', 'event')->first();
        $this->assertTrue($type->hierarchical);
        $this->assertSame('wydarzenia', $type->url_prefix);
        $this->assertSame(['event'], Taxonomy::where('key', 'city')->first()->contentTypes->pluck('key')->all());
        $this->assertSame(Term::where('slug', 'polska')->value('id'), Term::where('slug', 'wroclaw')->value('parent_id'));

        $child = Content::where('slug', 'dziecko')->first();
        $this->assertSame(Content::where('slug', 'rodzic')->value('id'), $child->parent_id);
        $this->assertSame('Rynek', $child->fields['place']);
        $this->assertSame(['wroclaw'], $child->terms->pluck('slug')->all());
        $this->assertSame(['title' => 'SEO', 'noindex' => true], $child->seo);
        $this->assertSame(Content::STATUS_PUBLISHED, $child->status);
        $this->assertNotNull($child->published_at);
        $this->assertSame('draft', Content::where('slug', 'rodzic')->value('status'));
    }

    public function test_dry_run_reports_but_writes_nothing(): void
    {
        $report = $this->imp($this->sample(), dry: true);

        $this->assertTrue($report['dry_run']);
        $this->assertFalse($report['applied']);
        $this->assertSame([], $report['errors']);
        $this->assertSame(2, $report['sections']['contents']['created']);
        $this->assertNull(ContentType::where('key', 'event')->first());
        $this->assertSame(0, FieldGroup::count());
        $this->assertSame(0, Content::count());
    }

    public function test_existing_items_are_skipped_or_updated(): void
    {
        $this->imp($this->sample());
        $changed = $this->sample();
        $changed['content_types'][0]['label'] = 'Zmieniony';
        $changed['field_groups'][0]['title'] = 'Nowy tytuł';
        $changed['contents'][1]['title'] = 'Rodzic 2';

        $skip = $this->imp($changed);
        $this->assertSame(1, $skip['sections']['content_types']['skipped']);
        $this->assertSame(0, $skip['sections']['contents']['created']);
        $this->assertSame('Wydarzenie', ContentType::where('key', 'event')->value('label'));
        $this->assertSame('Rodzic', Content::where('slug', 'rodzic')->value('title'));

        $update = $this->imp($changed, existing: 'update');
        $this->assertSame([], $update['errors']);
        $this->assertSame(1, $update['sections']['content_types']['updated']);
        $this->assertSame('Zmieniony', ContentType::where('key', 'event')->value('label'));
        $this->assertSame('Nowy tytuł', FieldGroup::where('key', 'group_event')->value('title'));
        $this->assertSame('Rodzic 2', Content::where('slug', 'rodzic')->value('title'));
        $this->assertSame(2, Content::count());
        $this->assertSame(1, FieldGroup::count());
    }

    public function test_any_error_rolls_back_the_whole_import(): void
    {
        $data = $this->sample();
        $data['contents'][] = ['type' => 'event', 'title' => 'Zła', 'fields' => [], 'terms' => ['city' => ['nie-ma']]];
        $data['contents'][] = ['type' => 'nie-ma', 'title' => 'Zła 2'];

        $report = $this->imp($data);

        $this->assertFalse($report['applied']);
        $this->assertCount(2, $report['errors']);
        $this->assertNull(ContentType::where('key', 'event')->first());
        $this->assertSame(0, Content::count());
        $this->assertSame(0, Term::count());
    }

    public function test_header_and_structure_errors(): void
    {
        $this->assertNotSame([], $this->imp(['content_types' => []])['errors']);
        $this->assertNotSame([], $this->imp(['lumin' => 99, 'content_types' => [['key' => 'a']]])['errors']);
        $this->assertNotSame([], $this->imp(['lumin' => 1])['errors']);
        $this->assertNotSame([], $this->imp(['lumin' => 1, 'contents' => ['a' => 1]])['errors']);
    }

    public function test_content_type_rules(): void
    {
        $base = ['label' => 'X', 'label_plural' => 'Xy'];

        foreach ([
            ['key' => 'Bad Key'] + $base,
            ['key' => 'ok', 'url_prefix' => 'cms'] + $base,
            ['key' => 'ok', 'url_prefix' => 'kategoria'] + $base,
            ['key' => 'ok', 'supports' => ['hack']] + $base,
        ] as $i => $type) {
            Taxonomy::where('key', 'category')->update(['rewrite_base' => 'kategoria']);
            $this->assertNotSame([], $this->imp(['lumin' => 1, 'content_types' => [$type]])['errors'], "case {$i}");
        }

        // Typ systemowy jest pomijany, nie nadpisywany.
        $report = $this->imp(['lumin' => 1, 'content_types' => [['key' => 'page', 'label' => 'Hack', 'label_plural' => 'Hack']]], existing: 'update');
        $this->assertSame(1, $report['sections']['content_types']['skipped']);
        $this->assertNotSame('Hack', ContentType::where('key', 'page')->value('label'));

        // Treści typu "part" nie importujemy.
        $this->assertNotSame([], $this->imp(['lumin' => 1, 'contents' => [['type' => 'part', 'title' => 'X']]])['errors']);
    }

    public function test_term_parent_cycles_and_flat_taxonomies(): void
    {
        $cycle = ['lumin' => 1, 'taxonomies' => [[
            'key' => 'tree', 'label' => 'D', 'label_plural' => 'D', 'hierarchical' => true,
            'terms' => [['name' => 'A', 'slug' => 'a', 'parent' => 'b'], ['name' => 'B', 'slug' => 'b', 'parent' => 'a']],
        ]]];
        $this->assertNotSame([], $this->imp($cycle)['errors']);
        $this->assertSame(0, Taxonomy::where('key', 'tree')->count());

        $flat = ['lumin' => 1, 'taxonomies' => [[
            'key' => 'flat', 'label' => 'F', 'label_plural' => 'F',
            'terms' => [['name' => 'A', 'slug' => 'a', 'parent' => 'b'], ['name' => 'B', 'slug' => 'b']],
        ]]];
        $report = $this->imp($flat);
        $this->assertSame([], $report['errors']);
        $this->assertNotSame([], $report['notes']);
        $this->assertNull(Term::where('slug', 'a')->value('parent_id'));
    }

    public function test_content_status_slug_and_field_validation(): void
    {
        $this->imp($this->sample());

        $past = $this->imp(['lumin' => 1, 'contents' => [['type' => 'page', 'title' => 'P', 'status' => 'scheduled', 'published_at' => now()->subDay()->toIso8601String()]]]);
        $this->assertNotSame([], $past['errors']);

        $future = $this->imp(['lumin' => 1, 'contents' => [['type' => 'page', 'title' => 'P', 'status' => 'scheduled', 'published_at' => now()->addDay()->toIso8601String()]]]);
        $this->assertSame([], $future['errors']);

        // Slug zajęty przez treść w koszu dostaje sufiks.
        Content::where('slug', 'rodzic')->first()->delete();
        $report = $this->imp(['lumin' => 1, 'contents' => [['type' => 'event', 'title' => 'Rodzic', 'slug' => 'rodzic']]]);
        $this->assertSame([], $report['errors']);
        $this->assertTrue(Content::where('slug', 'rodzic-2')->exists());

        // Pole z grupy musi przejść walidację typu (tu: tekst zbyt długi).
        $bad = $this->imp(['lumin' => 1, 'contents' => [['type' => 'event', 'title' => 'Z', 'fields' => ['place' => ['nie' => 'tekst']]]]]);
        $this->assertNotSame([], $bad['errors']);
    }

    public function test_export_then_import_round_trips(): void
    {
        $this->imp($this->sample());
        $json = app(Exporter::class)->build(['content_types', 'taxonomies', 'field_groups', 'contents']);

        $this->assertSame(1, $json['lumin']);
        $this->assertSame(['event'], array_column($json['content_types'], 'key'));
        $this->assertSame('polska', collect($json['taxonomies'])->firstWhere('key', 'city')['terms'][0]['parent'] ?? collect($json['taxonomies'])->firstWhere('key', 'city')['terms'][1]['parent']);
        $this->assertNotContains('part', array_column($json['contents'], 'type'));

        Content::query()->forceDelete();
        FieldGroup::query()->delete();
        Taxonomy::where('key', 'city')->delete();
        ContentType::where('key', 'event')->delete();

        $report = $this->imp(json_decode(json_encode($json), true));

        $this->assertSame([], $report['errors']);
        $this->assertSame('Rynek', Content::where('slug', 'dziecko')->first()->fields['place']);
        $this->assertSame(Content::where('slug', 'rodzic')->value('id'), Content::where('slug', 'dziecko')->value('parent_id'));
        $this->assertSame(['wroclaw'], Content::where('slug', 'dziecko')->first()->terms->pluck('slug')->all());
    }

    public function test_builder_content_is_rendered_to_html(): void
    {
        $project = ['pages' => [['frames' => [['component' => ['components' => [['tagName' => 'h2', 'type' => 'text', 'components' => [['type' => 'textnode', 'content' => 'Witaj']]]]]]]]]];
        $this->imp(['lumin' => 1, 'contents' => [['type' => 'page', 'title' => 'Landing', 'builder_data' => $project]]]);

        $this->assertStringContainsString('Witaj', (string) Content::where('slug', 'landing')->value('html_cache'));
    }

    public function test_panel_endpoints_and_permissions(): void
    {
        $this->actingAs($this->admin());

        $this->get('/cms/import')->assertOk()->assertInertia(fn ($p) => $p->component('tools/import')->where('report', null)->has('types'));

        $this->post('/cms/import', ['json' => json_encode($this->sample()), 'existing' => 'skip', 'dry_run' => true])
            ->assertOk()->assertInertia(fn ($p) => $p->where('report.dry_run', true)->where('report.applied', false)->where('report.sections.contents.created', 2));
        $this->assertSame(0, Content::count());

        $this->post('/cms/import', ['json' => json_encode($this->sample()), 'existing' => 'skip'])
            ->assertOk()->assertInertia(fn ($p) => $p->where('report.applied', true));
        $this->assertSame(2, Content::count());

        $this->post('/cms/import', ['json' => 'to nie json', 'existing' => 'skip'])->assertSessionHasErrors('json');
        $this->post('/cms/import', ['json' => '{}', 'existing' => 'x'])->assertSessionHasErrors('existing');

        $response = $this->get('/cms/export?sections[]=content_types&sections[]=contents&types[]=event');
        $response->assertOk()->assertHeader('content-disposition');
        $this->assertSame(['event'], array_column($response->json('content_types'), 'key'));
        $this->assertCount(2, $response->json('contents'));
        $this->get('/cms/export')->assertSessionHasErrors('sections');

        $this->actingAs(User::factory()->role('editor')->create());
        $this->get('/cms/import')->assertForbidden();
        $this->post('/cms/import', ['json' => '{}', 'existing' => 'skip'])->assertForbidden();
        $this->get('/cms/export?sections[]=contents')->assertForbidden();
    }
}
