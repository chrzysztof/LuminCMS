<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Builder\BuilderRenderer;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Shortcodes\Shortcodes;
use App\Cms\Themes\ThemeManager;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeBlocksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(base_path('themes/test-theme'));
        parent::tearDown();
    }

    private function project(array $components): array
    {
        return ['pages' => [['frames' => [['component' => ['components' => $components]]]]]];
    }

    private function hero(array $attrs): array
    {
        return ['type' => 'lm-block', 'block' => 'hero-banner', 'attrs' => $attrs];
    }

    public function test_registry_reads_blocks_of_the_active_theme_with_defaults(): void
    {
        app()->setLocale('pl');
        app()->forgetInstance(BlockRegistry::class);
        $registry = app(BlockRegistry::class);

        $this->assertSame(['hero-banner', 'testimonials'], array_keys($registry->all()));

        $hero = collect($registry->definitions())->firstWhere('name', 'hero-banner');
        $this->assertSame('Baner z przyciskiem', $hero['label']);
        $this->assertSame('Nagłówek banera', $hero['defaults']['heading']);
        $this->assertSame('left', $hero['defaults']['align']);
        $this->assertContains('image', array_column($hero['fields'], 'type'));
    }

    public function test_normalize_keeps_only_known_valid_fields(): void
    {
        $clean = app(BlockRegistry::class)->normalize('hero-banner', [
            'heading' => 'Tytuł', 'align' => 'diagonal', 'button_url' => 'not a url', 'nieznane' => 'x', 'text' => 'Opis',
        ]);

        $this->assertSame('Tytuł', $clean['heading']);
        $this->assertSame('Opis', $clean['text']);
        $this->assertArrayNotHasKey('nieznane', $clean);
        $this->assertArrayNotHasKey('button_url', $clean);
        $this->assertSame([], app(BlockRegistry::class)->normalize('nie-ma', ['heading' => 'x']));
    }

    public function test_renderer_stores_a_shortcode_and_the_block_is_rendered_escaped(): void
    {
        $html = app(BuilderRenderer::class)->render($this->project([$this->hero(['heading' => 'Hej <b>x</b>', 'align' => 'center'])]));

        $this->assertStringStartsWith('[block name="hero-banner" data="', $html);
        $this->assertStringNotContainsString('<b>', $html);

        $out = app(Shortcodes::class)->render($html);
        $this->assertStringContainsString('lm-hero-banner--center', $out);
        $this->assertStringContainsString('Hej &lt;b&gt;x&lt;/b&gt;', $out);
        $this->assertStringNotContainsString('Hej <b>', $out);
    }

    public function test_unknown_blocks_and_tampered_data_render_nothing(): void
    {
        $this->assertSame('', app(BuilderRenderer::class)->render($this->project([['type' => 'lm-block', 'block' => '../../x', 'attrs' => []]])));
        $this->assertSame('', app(Shortcodes::class)->render('[block name="nie-ma" data="abc"]'));
        $this->assertSame('', app(Shortcodes::class)->render('[block name="../../etc/passwd" data="e30"]'));

        // Uszkodzone dane: blok renderuje się z pustymi wartościami (nie ma wyjątku).
        $this->assertStringContainsString('lm-hero-banner', app(Shortcodes::class)->render('[block name="hero-banner" data="@@@"]'));
    }

    public function test_repeater_values_reach_the_template(): void
    {
        $out = app(BlockRegistry::class)->render('testimonials', ['title' => 'Opinie', 'items' => [['quote' => 'Super!', 'author' => 'Anna'], ['quote' => 'Polecam', 'author' => '']]]);

        $this->assertStringContainsString('Super!', $out);
        $this->assertStringContainsString('Anna', $out);
        $this->assertSame(2, substr_count($out, 'lm-testimonials__item"'));
        $this->assertSame('', app(BlockRegistry::class)->render('testimonials', ['title' => 'Pusto']));
    }

    public function test_saved_builder_page_shows_the_block_publicly_with_theme_css(): void
    {
        $admin = User::factory()->role(RoleRegistry::ADMINISTRATOR)->create();
        $page = Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Oferta', 'slug' => 'oferta',
            'status' => 'published', 'published_at' => now()->subDay(), 'fields' => [], 'author_id' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/cms/content/page/{$page->id}/builder", ['project' => $this->project([$this->hero(['heading' => 'Witaj'])])])->assertOk();

        $this->assertStringStartsWith('[block', $page->refresh()->html_cache);
        $this->get('/oferta')->assertOk()->assertSee('lm-hero-banner', false)->assertSee('Witaj')->assertSee('theme-blocks.css', false);
        $this->get('/theme-blocks.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8')->assertSee('.lm-hero-banner', false)->assertSee('.lm-testimonials', false);

        $this->get("/cms/content/page/{$page->id}/builder")->assertOk()->assertInertia(fn ($p) => $p
            ->component('content/builder')->has('themeBlocks', 2)->where('themeBlocks.0.name', 'hero-banner'));
    }

    public function test_preview_endpoint_renders_normalized_block_and_checks_access(): void
    {
        $this->actingAs(User::factory()->role('contributor')->create());

        $this->postJson('/cms/blocks/preview', ['block' => 'hero-banner', 'attrs' => ['heading' => 'Podgląd', 'align' => 'zły']])
            ->assertOk()->assertJsonPath('attrs.heading', 'Podgląd')->assertJsonMissingPath('attrs.align')
            ->assertJsonPath('html', fn ($h) => str_contains($h, 'Podgląd'));
        $this->postJson('/cms/blocks/preview', ['block' => 'nie-ma'])->assertNotFound();

        $this->actingAs(User::factory()->role('subscriber')->create());
        $this->postJson('/cms/blocks/preview', ['block' => 'hero-banner'])->assertForbidden();
    }

    public function test_blocks_belong_to_the_active_theme(): void
    {
        File::ensureDirectoryExists(base_path('themes/test-theme/blocks/box'));
        File::put(base_path('themes/test-theme/theme.json'), json_encode(['name' => 'T']));
        File::put(base_path('themes/test-theme/blocks/box/block.json'), json_encode(['label' => 'Box', 'fields' => [['type' => 'text', 'name' => 'x', 'label' => 'X'], ['type' => 'wymyslony', 'name' => 'y']]]));
        File::put(base_path('themes/test-theme/blocks/box/render.blade.php'), '<i>{{ $attrs["x"] }}</i>');

        app(ThemeManager::class)->activate('test-theme');
        $registry = app(BlockRegistry::class);

        $this->assertSame(['box'], array_keys($registry->all()));
        $this->assertCount(1, $registry->get('box')['fields']);
        $this->assertSame('<i>Cześć</i>', $registry->render('box', ['x' => 'Cześć']));
        $this->assertSame('', $registry->render('hero-banner', ['heading' => 'x']));
    }

    public function test_block_and_field_labels_are_translated_for_the_panel_language(): void
    {
        app()->setLocale('de');
        app()->forgetInstance(BlockRegistry::class);

        $hero = collect(app(BlockRegistry::class)->definitions())->firstWhere('name', 'hero-banner');

        $this->assertSame('Banner mit Button', $hero['label']);
        $this->assertSame('Hintergrundbild', collect($hero['fields'])->firstWhere('name', 'image')['label']);
        $this->assertSame('Links', collect($hero['fields'])->firstWhere('name', 'align')['choices']['left'] ?? null);
    }
}
