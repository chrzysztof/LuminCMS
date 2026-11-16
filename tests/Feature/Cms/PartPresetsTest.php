<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Settings\Settings;
use App\Cms\Themes\Parts;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PartPresetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);

        Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Start', 'slug' => 'home',
            'status' => 'published', 'published_at' => now()->subMinute(), 'html_cache' => '<p>Treść</p>',
        ]);
    }

    public function test_theme_offers_four_headers_and_four_footers(): void
    {
        $presets = app(Parts::class)->presets();

        $this->assertCount(4, array_filter($presets, fn ($p) => $p['slot'] === 'header'));
        $this->assertCount(4, array_filter($presets, fn ($p) => $p['slot'] === 'footer'));
        $this->assertNotNull(app(Parts::class)->presetHtml('header', 'classic'));
        $this->assertNull(app(Parts::class)->presetHtml('header', '../x'));
        $this->assertNull(app(Parts::class)->presetHtml('header', 'nie-ma'));
    }

    public function test_parts_panel_lists_presets_and_using_one_creates_and_assigns_a_part(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/cms/parts')->assertInertia(fn (Assert $p) => $p->component('parts/index')->has('presets', 8));

        $response = $this->post('/cms/parts/presets', ['slot' => 'header', 'key' => 'dark']);
        $part = Content::where('title', 'Ciemny')->firstOrFail();

        $response->assertRedirect("/cms/content/part/{$part->id}/builder?preset=header-dark");
        $this->assertSame($part->id, app(Parts::class)->assignedId('header'));

        $this->post('/cms/parts/presets', ['slot' => 'header', 'key' => 'nie-ma'])->assertNotFound();
    }

    public function test_builder_seeds_a_fresh_part_with_the_preset_only_once(): void
    {
        $this->actingAs(User::factory()->create());
        $part = app(Parts::class)->createFromPreset('footer', 'columns');

        $this->get("/cms/content/part/{$part->id}/builder?preset=footer-columns")
            ->assertInertia(fn (Assert $p) => $p
                ->where('project', null)
                ->where('seedHtml', fn ($html) => str_contains($html, 'data-gjs-block="nav-menu"') && str_starts_with($html, '<footer'))
                ->has('themeStyles', 3));

        // Bez parametru albo po pierwszym zapisie część nie jest nadpisywana wzorem.
        $this->get("/cms/content/part/{$part->id}/builder")->assertInertia(fn (Assert $p) => $p->where('seedHtml', null));

        $part->update(['builder_data' => ['pages' => []]]);
        $this->get("/cms/content/part/{$part->id}/builder?preset=footer-columns")->assertInertia(fn (Assert $p) => $p->where('seedHtml', null));
    }

    public function test_preview_renders_theme_blocks_into_markup(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/cms/parts/presets/header/classic/preview')->assertOk();

        $response->assertSee('lm-header', false)->assertSee('lm-site-header__brand', false)->assertDontSee('data-gjs', false);
        $this->assertStringContainsString("script-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->get('/cms/parts/presets/header/nie-ma/preview')->assertNotFound();
    }

    public function test_presets_are_for_administrators_only(): void
    {
        $this->actingAs(User::factory()->role('editor')->create());

        $this->post('/cms/parts/presets', ['slot' => 'header', 'key' => 'classic'])->assertForbidden();
        $this->get('/cms/parts/presets/header/classic/preview')->assertForbidden();
    }

    public function test_layout_uses_a_preset_root_as_is_and_wraps_other_parts(): void
    {
        $parts = app(Parts::class);
        $header = $parts->create('Nagłówek', 'header');
        $header->update(['html_cache' => '<header class="lm-site-header lm-header-dark"><p>Ciemny</p></header>']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<header class="lm-site-header lm-header-dark">', $html);
        $this->assertStringNotContainsString('<header class="lm-site-header"><div class="lm-container">', $html);

        // Część bez własnego <header> (np. zbudowana od zera) dostaje opakowanie motywu.
        $header->update(['html_cache' => '<p>Własny</p>']);
        $compact = preg_replace('/>\s+</', '><', $this->get('/')->assertOk()->getContent());
        $this->assertStringContainsString('<header class="lm-site-header"><div class="lm-container"><p>Własny</p>', $compact);
    }

    public function test_cookie_link_is_injected_inside_a_preset_footer(): void
    {
        app(Settings::class)->save('site', ['ga_id' => 'G-ABCD1234', 'consent_required' => true] + app(Settings::class)->group('site'));
        $footer = app(Parts::class)->create('Stopka', 'footer');
        $footer->update(['html_cache' => '<footer class="lm-site-footer lm-footer"><p>Stopka</p></footer>']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<p>Stopka</p><div class="lm-container lm-footer__cookies"><a href="\#" data-lm-cookie-settings>[^<]+</a></div></footer>~', $html);
        $this->assertSame(1, substr_count($html, '<footer class="lm-site-footer'));
    }

    public function test_social_block_stays_empty_on_the_public_site_without_links(): void
    {
        $footer = app(Parts::class)->create('Stopka', 'footer');
        $footer->update(['html_cache' => '<footer class="lm-site-footer"><p>X</p>[block name="social-links"]</footer>']);

        $this->get('/')->assertOk()->assertDontSee('lm-social', false);
    }
}
