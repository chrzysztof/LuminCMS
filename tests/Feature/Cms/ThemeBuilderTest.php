<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Cms\Themes\Templates;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThemeBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Content $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);

        $this->post = Content::create([
            'content_type_id' => ContentType::where('key', 'post')->value('id'), 'title' => 'Mój wpis', 'slug' => 'moj-wpis', 'status' => 'published',
            'published_at' => now()->subDay(), 'excerpt' => 'Krótko o wpisie', 'html_cache' => '<p>Treść główna</p>', 'fields' => [],
        ]);
    }

    private function template(string $key, string $html): Content
    {
        $part = app(Templates::class)->create($key);
        $part->update(['html_cache' => $html]);

        return $part;
    }

    public function test_slots_cover_types_archives_and_taxonomies(): void
    {
        $keys = array_column(app(Templates::class)->slots(), 'key');

        $this->assertContains('single:post', $keys);
        $this->assertContains('single:page', $keys);
        $this->assertContains('archive:post', $keys);
        $this->assertNotContains('archive:page', $keys);
        $this->assertNotContains('single:part', $keys);
        $this->assertContains('taxonomy:category', $keys);
    }

    public function test_single_template_wraps_content_with_bindings_and_blocks(): void
    {
        $this->get('/blog/moj-wpis')->assertOk()->assertSee('Treść główna')->assertSee('lm-article');

        $this->template('single:post', '<main class="lm-tpl"><h1>[field name="_title" format="text"]Tytuł[/field]</h1>[block name="post-content" data=""][block name="post-meta" data=""]</main>');

        $this->get('/blog/moj-wpis')->assertOk()
            ->assertSee('<main class="lm-tpl"><h1>Mój wpis</h1>', false)
            ->assertSee('<div class="lm-article__body"><p>Treść główna</p></div>', false)
            ->assertSee('lm-post-meta', false)
            ->assertDontSee('lm-article__lead', false);

        // Inne typy nie są dotknięte, a szablon strony z motywu ma pierwszeństwo.
        $page = Content::create(['content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'O nas', 'slug' => 'o-nas', 'status' => 'published', 'published_at' => now(), 'html_cache' => '<p>Strona</p>']);
        $this->get('/o-nas')->assertOk()->assertSee('Strona')->assertDontSee('lm-tpl', false);
    }

    public function test_post_content_block_is_safe_in_editor_preview_and_never_recurses(): void
    {
        $registry = app(BlockRegistry::class);

        $this->assertStringContainsString('lm-template-placeholder', $registry->render('post-content', []));

        $this->post->update(['html_cache' => '[block name="post-content" data=""]po drodze']);
        $out = $registry->render('post-content', [], $this->post);
        $this->assertStringContainsString('po drodze', $out);
        $this->assertSame(1, substr_count($out, 'lm-article__body'));
    }

    public function test_archive_and_taxonomy_templates_receive_the_listing(): void
    {
        $this->get('/blog')->assertOk()->assertSee('lm-archive-item"', false);

        $this->template('archive:post', '<div class="lm-tpl">[block name="archive-heading" data=""][block name="archive-items" data=""]</div>');
        $this->get('/blog')->assertOk()->assertSee('lm-tpl', false)->assertSee('<h1>Wpisy</h1>', false)->assertSee('Mój wpis')->assertDontSee('lm-archive-item"', false);

        $term = Term::create(['taxonomy_id' => Taxonomy::where('key', 'category')->value('id'), 'name' => 'Nowości', 'slug' => 'nowosci', 'description' => 'Opis kategorii']);
        $this->post->terms()->attach($term);
        $base = Taxonomy::where('key', 'category')->first()->base();

        $this->template('taxonomy:category', '<div class="lm-tpl-tax">[block name="archive-heading" data=""][block name="archive-items" data=""]</div>');
        $this->get("/{$base}/nowosci")->assertOk()->assertSee('lm-tpl-tax', false)->assertSee('<h1>Nowości</h1>', false)->assertSee('Opis kategorii')->assertSee('Mój wpis');
    }

    public function test_panel_creates_assigns_and_unassigns_templates(): void
    {
        $this->actingAs(User::factory()->create());

        $r = $this->get('/cms/templates');
        $r->assertInertia(fn (Assert $p) => $p->component('templates/index')->where('slots.0.content_id', null));

        $response = $this->post('/cms/templates', ['key' => 'single:post']);
        $part = Content::where('title', 'like', '%Wpisy%')->orWhere('title', 'like', 'Pojedyncze%')->firstOrFail();
        $response->assertRedirect("/cms/content/part/{$part->id}/builder?preset=template-single");
        $this->assertSame($part->id, app(Templates::class)->assignedId('single:post'));

        $this->get("/cms/content/part/{$part->id}/builder?preset=template-single")
            ->assertInertia(fn (Assert $p) => $p->where('seedHtml', fn ($html) => str_contains($html, 'data-gjs-block="page-header"') && str_contains($html, 'post-content') && str_contains($html, 'post-meta')));

        $this->put('/cms/templates', ['key' => 'single:post', 'content_id' => null])->assertRedirect();
        $this->assertNull(app(Templates::class)->assignedId('single:post'));

        $this->put('/cms/templates', ['key' => 'single:nie-ma', 'content_id' => $part->id])->assertNotFound();

        // Usunięcie części odpina ją od szablonów.
        app(Templates::class)->assign('archive:post', $part->id);
        $this->delete("/cms/parts/{$part->id}");
        $this->assertNull(app(Templates::class)->assignedId('archive:post'));
    }

    public function test_only_administrators_manage_templates(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'author']));

        $this->get('/cms/templates')->assertForbidden();
        $this->post('/cms/templates', ['key' => 'single:post'])->assertForbidden();
    }
}
