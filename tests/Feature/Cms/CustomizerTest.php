<?php

namespace Tests\Feature\Cms;

use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Themes\Customizer;
use App\Cms\Themes\ThemeManager;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CustomizerTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->role(RoleRegistry::ADMINISTRATOR)->create();
    }

    private function publicPage(): void
    {
        Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Start', 'slug' => 'start',
            'status' => 'published', 'published_at' => now()->subDay(), 'fields' => [], 'html_cache' => '<p>Start</p>',
        ]);
    }

    public function test_defaults_produce_no_css_override_but_keep_the_style_tag(): void
    {
        $this->publicPage();

        $this->assertSame('', app(Customizer::class)->css());
        $this->get('/start')->assertOk()->assertSee('<style id="lm-theme-vars"></style>', false);
        $this->get('/theme-vars.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    }

    public function test_saved_values_are_emitted_as_css_variables_and_only_when_changed(): void
    {
        $this->actingAs($this->admin());
        $this->publicPage();

        $this->put('/cms/customize', ['values' => ['primary' => '#FF0000', 'text' => '#111114', 'container' => 60, 'radius' => 0.5]])->assertRedirect();

        $css = app(Customizer::class)->css();
        $this->assertStringContainsString('--lm-primary:#ff0000;', $css);
        $this->assertStringContainsString('--lm-container:60rem;', $css);
        $this->assertStringContainsString('--lm-radius:0.5rem;', $css);
        $this->assertStringNotContainsString('--lm-text', $css);

        $this->get('/start')->assertSee('--lm-primary:#ff0000;', false);
        $this->get('/theme-vars.css')->assertSee('--lm-primary:#ff0000;', false);
    }

    public function test_invalid_values_are_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin());

        foreach ([
            ['primary' => 'red'],
            ['primary' => '#ff0000;} body{display:none'],
            ['font' => 'Comic Sans, cursive'],
            ['container' => 500],
            ['radius' => 'abc'],
        ] as $values) {
            $this->put('/cms/customize', ['values' => $values])->assertSessionHasErrors('values.'.array_key_first($values));
        }

        $this->assertSame('', app(Customizer::class)->css());
    }

    public function test_unknown_keys_are_ignored_and_select_must_come_from_theme_options(): void
    {
        $this->actingAs($this->admin());
        $serif = "Georgia, 'Times New Roman', serif";

        $this->put('/cms/customize', ['values' => ['nie_ma' => '#000000', 'font' => $serif]])->assertRedirect();

        $this->assertSame(['font' => $serif], app(Customizer::class)->saved());
        $this->assertStringContainsString('--lm-font:'.$serif.';', app(Customizer::class)->css());
    }

    public function test_reset_restores_defaults(): void
    {
        $this->actingAs($this->admin());
        $this->put('/cms/customize', ['values' => ['primary' => '#00ff00']]);
        $this->assertNotSame('', app(Customizer::class)->css());

        $this->delete('/cms/customize')->assertRedirect();
        $this->assertSame('', app(Customizer::class)->css());
    }

    public function test_values_are_stored_per_theme(): void
    {
        File::ensureDirectoryExists(base_path('themes/test-theme/views'));
        File::put(base_path('themes/test-theme/theme.json'), json_encode(['name' => 'T', 'tokens' => [
            ['key' => 'primary', 'type' => 'color', 'default' => '#000000', 'css' => '--t-primary', 'label' => 'P'],
            ['key' => 'evil', 'type' => 'color', 'default' => '#000000', 'css' => 'x;}body{', 'label' => 'E'],
        ]]));
        $this->actingAs($this->admin());
        $this->put('/cms/customize', ['values' => ['primary' => '#123456']]);

        app(ThemeManager::class)->activate('test-theme');

        $this->assertSame([], app(Customizer::class)->saved());
        $this->assertCount(1, app(Customizer::class)->tokens());
        $this->put('/cms/customize', ['values' => ['primary' => '#abcdef']]);
        $this->assertSame('--t-primary:#abcdef;', trim(substr(app(Customizer::class)->css(), 6, -1)));

        app(ThemeManager::class)->activate('lumin-default');
        $this->assertStringContainsString('#123456', app(Customizer::class)->css());
    }

    public function test_customize_screen_is_admin_only_and_lists_tokens(): void
    {
        $this->actingAs(User::factory()->role('editor')->create());
        $this->get('/cms/customize')->assertForbidden();
        $this->put('/cms/customize', ['values' => []])->assertForbidden();

        $this->actingAs($this->admin());
        $this->get('/cms/customize')->assertOk()->assertInertia(fn ($p) => $p
            ->component('customize/index')
            ->has('tokens', 12)->where('previewUrl', url('/blog'))->where('values.primary', '#111114')->where('tokens.0.group', 'Kolory'));
    }
}
