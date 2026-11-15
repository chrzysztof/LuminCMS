<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Importer\Exporter;
use App\Cms\Importer\Importer;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
        $this->admin = User::factory()->create();
    }

    private function payload(array $o = []): array
    {
        return $o + ['key' => 'realizacja', 'label' => 'Realizacja', 'label_plural' => 'Realizacje', 'icon' => 'briefcase',
            'supports' => ['title', 'editor', 'thumbnail', 'seo'], 'taxonomies' => ['category']];
    }

    public function test_admin_creates_a_custom_type_that_shows_in_menu_and_has_defaults(): void
    {
        $this->actingAs($this->admin)->get('/cms/content-types')->assertOk();

        $this->actingAs($this->admin)->post('/cms/content-types', $this->payload())->assertSessionDoesntHaveErrors();

        $type = ContentType::where('key', 'realizacja')->firstOrFail();
        $this->assertFalse($type->is_system);
        $this->assertSame('realizacja', $type->url_prefix);
        $this->assertSame(['category'], $type->taxonomies()->pluck('key')->all());

        $this->actingAs($this->admin)->get('/cms/content/realizacja')->assertOk();
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertInertia(fn ($p) => $p->where('contentTypes', fn ($t) => collect($t)->contains('key', 'realizacja')));
    }

    public function test_validation_rejects_duplicates_reserved_keys_and_taken_prefixes(): void
    {
        $this->actingAs($this->admin)->post('/cms/content-types', $this->payload(['key' => 'post']))->assertSessionHasErrors('key');
        $this->actingAs($this->admin)->post('/cms/content-types', $this->payload(['key' => 'part']))->assertSessionHasErrors('key');
        $this->actingAs($this->admin)->post('/cms/content-types', $this->payload(['key' => 'Zły Klucz']))->assertSessionHasErrors('key');
        $this->actingAs($this->admin)->post('/cms/content-types', $this->payload(['url_prefix' => 'cms']))->assertSessionHasErrors('url_prefix');

        $this->actingAs($this->admin)->post('/cms/content-types', $this->payload())->assertSessionDoesntHaveErrors();
        $this->actingAs($this->admin)->post('/cms/content-types', $this->payload(['key' => 'oferta', 'url_prefix' => 'realizacja']))
            ->assertSessionHasErrors('url_prefix');
    }

    public function test_update_keeps_key_and_system_types_keep_their_structure(): void
    {
        $this->actingAs($this->admin)->post('/cms/content-types', $this->payload());

        $this->actingAs($this->admin)->put('/cms/content-types/realizacja', $this->payload(['key' => null, 'label_plural' => 'Nasze realizacje', 'hierarchical' => true, 'url_prefix' => 'portfolio']))
            ->assertSessionDoesntHaveErrors();
        $type = ContentType::where('key', 'realizacja')->first();
        $this->assertSame('Nasze realizacje', $type->label_plural);
        $this->assertTrue($type->hierarchical);
        $this->assertSame('portfolio', $type->url_prefix);

        $this->actingAs($this->admin)->put('/cms/content-types/realizacja', $this->payload(['key' => 'inny']))->assertSessionHasErrors('key');

        $page = ContentType::where('key', 'page')->first();
        $this->actingAs($this->admin)->put('/cms/content-types/page', ['label' => 'Strona', 'label_plural' => 'Strony', 'hierarchical' => false, 'url_prefix' => 'x', 'supports' => ['title']])
            ->assertSessionDoesntHaveErrors();
        $this->assertTrue($page->fresh()->hierarchical);
        $this->assertNull($page->fresh()->url_prefix);
    }

    public function test_delete_is_blocked_for_system_types_and_types_with_content(): void
    {
        $this->actingAs($this->admin)->post('/cms/content-types', $this->payload());
        $type = ContentType::where('key', 'realizacja')->first();

        $this->actingAs($this->admin)->delete('/cms/content-types/post')->assertForbidden();

        Content::create(['content_type_id' => $type->id, 'title' => 'X', 'slug' => 'x', 'status' => 'draft']);
        $this->actingAs($this->admin)->delete('/cms/content-types/realizacja');
        $this->assertNotNull(ContentType::where('key', 'realizacja')->first());

        Content::where('content_type_id', $type->id)->forceDelete();
        $this->actingAs($this->admin)->delete('/cms/content-types/realizacja');
        $this->assertNull(ContentType::where('key', 'realizacja')->first());
    }

    public function test_only_administrators_manage_types(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/cms/content-types')->assertForbidden();
        $this->actingAs($editor)->post('/cms/content-types', $this->payload())->assertForbidden();
    }

    public function test_import_creates_custom_type_with_defaults_and_content_is_reachable_publicly(): void
    {
        $data = ['lumin' => 1,
            'content_types' => [['key' => 'oferta', 'label' => 'Oferta', 'label_plural' => 'Oferty', 'supports' => ['title', 'editor', 'seo']]],
            'contents' => [['type' => 'oferta', 'title' => 'Pakiet', 'slug' => 'pakiet', 'status' => 'published']]];

        $report = app(Importer::class)->run($data, false, 'skip', $this->admin);

        $this->assertSame([], $report->errors ?? []);
        $type = ContentType::where('key', 'oferta')->firstOrFail();
        $this->assertSame('oferta', $type->url_prefix);
        $this->assertSame('post', $type->capability_type);
        $this->get('/oferta/pakiet')->assertOk();

        // Eksport zawiera typ razem z uprawnieniami i da się go zaimportować z powrotem.
        $out = app(Exporter::class)->build(['content_types']);
        $this->assertSame('post', $out['content_types'][0]['capability_type']);
    }
}
