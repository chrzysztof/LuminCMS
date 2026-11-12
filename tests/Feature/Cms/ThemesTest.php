<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Taxonomy\Taxonomy;
use App\Cms\Taxonomy\Term;
use App\Cms\Themes\ThemeManager;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemesTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpTheme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
        $this->tmpTheme = base_path('themes/test-theme');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmpTheme);
        parent::tearDown();
    }

    private function page(string $title, string $slug, array $extra = []): Content
    {
        return Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => $title, 'slug' => $slug,
            'status' => 'published', 'published_at' => now()->subDay(), 'fields' => [], 'html_cache' => '<p>Treść '.$title.'</p>', ...$extra,
        ]);
    }

    private function makePost(string $title, string $slug, array $extra = []): Content
    {
        return $this->page($title, $slug, ['content_type_id' => ContentType::where('key', 'post')->value('id')] + $extra);
    }

    public function test_default_theme_is_active_and_lists_templates_per_type(): void
    {
        $themes = app(ThemeManager::class);

        $this->assertSame('lumin-default', $themes->active()->slug);
        $this->assertSame(['default', 'landing'], array_column($themes->templatesFor(ContentType::where('key', 'page')->first()), 'key'));
        $this->assertSame(['default'], array_column($themes->templatesFor(ContentType::where('key', 'post')->first()), 'key'));
    }

    public function test_page_template_changes_the_layout(): void
    {
        $this->actingAs(User::factory()->role(RoleRegistry::ADMINISTRATOR)->create());
        $page = $this->page('Oferta', 'oferta');

        $this->get('/oferta')->assertOk()->assertSee('lm-site-header', false);

        $this->put("/cms/content/page/{$page->id}", ['title' => 'Oferta', 'status' => 'published', 'fields' => [], 'template' => 'landing'])->assertRedirect();
        $this->assertSame('landing', $page->refresh()->template);
        $this->get('/oferta')->assertOk()->assertDontSee('lm-site-header', false)->assertSee('Treść Oferta');

        // Powrót do domyślnego czyści kolumnę.
        $this->put("/cms/content/page/{$page->id}", ['title' => 'Oferta', 'status' => 'published', 'fields' => [], 'template' => 'default'])->assertRedirect();
        $this->assertNull($page->refresh()->template);
    }

    public function test_unknown_or_foreign_template_is_rejected(): void
    {
        $this->actingAs(User::factory()->role(RoleRegistry::ADMINISTRATOR)->create());

        // "landing" jest tylko dla stron.
        $this->post('/cms/content/post', ['title' => 'W', 'status' => 'draft', 'fields' => [], 'template' => 'landing'])->assertSessionHasErrors('template');
        $this->post('/cms/content/page', ['title' => 'S', 'status' => 'draft', 'fields' => [], 'template' => '../../etc'])->assertSessionHasErrors('template');
    }

    public function test_blog_archive_lists_published_posts_with_pagination(): void
    {
        foreach (range(1, 12) as $i) {
            $this->makePost("Wpis {$i}", "wpis-{$i}", ['published_at' => now()->subDays(20 - $i)]);
        }
        $this->makePost('Szkic', 'szkic', ['status' => 'draft', 'published_at' => null]);

        $first = $this->get('/blog')->assertOk();
        $first->assertSee('Wpis 12')->assertDontSee('Wpis 1<', false)->assertDontSee('Szkic')->assertSee('<title>Wpisy', false)
            ->assertSee('rel="next"', false);

        $this->get('/blog?page=2')->assertOk()->assertSee('Wpis 1<', false)->assertSee('?page=2', false);
        $this->get('/blog?page=9')->assertNotFound();
    }

    public function test_term_page_lists_content_of_term_and_its_children(): void
    {
        $tax = Taxonomy::where('key', 'category')->first();
        $parent = Term::create(['taxonomy_id' => $tax->id, 'name' => 'Nowości', 'slug' => 'nowosci', 'description' => 'Co słychać']);
        $child = Term::create(['taxonomy_id' => $tax->id, 'name' => 'Dziecko', 'slug' => 'dziecko', 'parent_id' => $parent->id]);
        $other = Term::create(['taxonomy_id' => $tax->id, 'name' => 'Inne', 'slug' => 'inne']);
        $this->makePost('Z rodzica', 'z-rodzica')->terms()->attach($parent);
        $this->makePost('Z dziecka', 'z-dziecka')->terms()->attach($child);
        $this->makePost('Z innej', 'z-innej')->terms()->attach($other);

        $this->get('/kategoria/nowosci')->assertOk()
            ->assertSee('Z rodzica')->assertSee('Z dziecka')->assertDontSee('Z innej')->assertSee('Co słychać');
        $this->get('/kategoria/nie-ma')->assertNotFound();
        $this->get('/tag/nowosci')->assertNotFound();

        // Pojedynczy wpis linkuje do swoich terminów.
        $this->get('/blog/z-rodzica')->assertOk()->assertSee(url('/kategoria/nowosci'), false);
    }

    public function test_sitemap_includes_archives_and_non_empty_terms(): void
    {
        $tax = Taxonomy::where('key', 'category')->first();
        $used = Term::create(['taxonomy_id' => $tax->id, 'name' => 'Używany', 'slug' => 'uzywany']);
        Term::create(['taxonomy_id' => $tax->id, 'name' => 'Pusty', 'slug' => 'pusty']);
        $this->makePost('Wpis', 'wpis')->terms()->attach($used);

        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/blog'), false)->assertSee(url('/kategoria/uzywany'), false)
            ->assertDontSee('/kategoria/pusty', false);
    }

    public function test_taxonomy_base_cannot_shadow_content_archive_or_panel(): void
    {
        $this->actingAs(User::factory()->role(RoleRegistry::ADMINISTRATOR)->create());
        $payload = ['label' => 'T', 'label_plural' => 'T'];

        $this->post('/cms/taxonomies', ['key' => 'a', 'rewrite_base' => 'blog'] + $payload)->assertSessionHasErrors('rewrite_base');
        $this->post('/cms/taxonomies', ['key' => 'b', 'rewrite_base' => 'cms'] + $payload)->assertSessionHasErrors('rewrite_base');
        $this->post('/cms/taxonomies', ['key' => 'c', 'rewrite_base' => 'tag'] + $payload)->assertSessionHasErrors('rewrite_base');
        $this->post('/cms/taxonomies', ['key' => 'd', 'rewrite_base' => 'temat'] + $payload)->assertRedirect();
    }

    public function test_theme_assets_are_served_without_escaping_the_folder(): void
    {
        $this->get('/theme-assets/lumin-default/theme.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
        $this->get('/theme-assets/lumin-default/../theme.json')->assertNotFound();
        $this->get('/theme-assets/lumin-default/%2e%2e/theme.json')->assertNotFound();
        $this->get('/theme-assets/nie-ma/theme.css')->assertNotFound();
    }

    public function test_activating_another_theme_overrides_views_and_falls_back_for_the_rest(): void
    {
        File::ensureDirectoryExists($this->tmpTheme.'/views/templates');
        File::put($this->tmpTheme.'/theme.json', json_encode(['name' => 'Test', 'templates' => [['key' => 'default', 'label' => 'Domyślny']]]));
        File::put($this->tmpTheme.'/views/templates/default.blade.php', '<h1>Motyw testowy: {{ $content->title }}</h1>');

        $this->page('Strona', 'strona');
        app(ThemeManager::class)->activate('test-theme');

        // Nadpisany szablon pochodzi z nowego motywu, a archiwum (brak pliku) z domyślnego.
        $this->get('/strona')->assertOk()->assertSee('Motyw testowy: Strona');
        $this->makePost('Wpis', 'wpis-x');
        $this->get('/blog')->assertOk()->assertSee('Wpis');

        $this->assertSame('test-theme', app(ThemeManager::class)->active()->slug);
    }

    public function test_theme_command_lists_and_activates(): void
    {
        $this->artisan('cms:theme')->expectsOutputToContain('lumin-default')->assertExitCode(0);
        $this->artisan('cms:theme nie-ma')->assertExitCode(1);
        $this->artisan('cms:theme lumin-default')->assertExitCode(0);
    }
}
