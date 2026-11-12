<?php

namespace Tests\Feature\Cms;

use App\Cms\Builder\BlockRegistry;
use App\Cms\Content\Content;
use App\Cms\Content\ContentType;
use App\Cms\Roles\RoleRegistry;
use App\Cms\Themes\Customizer;
use App\Cms\Themes\ThemeManager;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class ThemeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
    }

    protected function tearDown(): void
    {
        foreach (glob(base_path('themes/*')) ?: [] as $dir) {
            if (basename($dir) !== ThemeManager::DEFAULT) {
                File::deleteDirectory($dir);
            }
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->role(RoleRegistry::ADMINISTRATOR)->create();
    }

    private function theme(string $slug, array $manifest, array $files = []): void
    {
        File::ensureDirectoryExists(base_path("themes/{$slug}"));
        File::put(base_path("themes/{$slug}/theme.json"), json_encode($manifest));

        foreach ($files as $path => $content) {
            File::ensureDirectoryExists(dirname(base_path("themes/{$slug}/{$path}")));
            File::put(base_path("themes/{$slug}/{$path}"), $content);
        }

        app(ThemeManager::class)->refresh();
    }

    /** @param  array<string, string>  $files  nazwa w archiwum => treść */
    private function zip(array $files, string $name = 'mini.zip'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tz');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($files as $entry => $content) {
            $zip->addFromString($entry, $content);
        }

        $zip->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    private function validFiles(string $root = 'mini/', array $manifest = []): array
    {
        return [
            $root.'theme.json' => json_encode($manifest + ['name' => 'Mini', 'version' => '1.2.0', 'author' => 'Ja']),
            $root.'views/layout.blade.php' => '<html>@yield("content")</html>',
            $root.'assets/theme.css' => 'body{}',
        ];
    }

    public function test_child_theme_inherits_views_manifest_assets_and_blocks(): void
    {
        $this->theme('child-theme', [
            'name' => 'Child', 'parent' => 'lumin-default',
            'templates' => [['key' => 'wide', 'label' => 'Szeroki', 'types' => ['page']]],
            'tokens' => [
                ['key' => 'primary', 'type' => 'color', 'default' => '#ff0000', 'css' => '--lm-primary', 'label' => 'Kolor', 'group' => 'Kolory'],
                ['key' => 'accent', 'type' => 'color', 'default' => '#00ff00', 'css' => '--lm-accent', 'label' => 'Akcent'],
            ],
        ], [
            'views/templates/default.blade.php' => '@extends("theme::layout")@section("content")<h1>Potomny: {{ $content->title }}</h1>@endsection',
            'assets/child.css' => 'a{}',
            'blocks/hero-banner/block.json' => json_encode(['label' => 'Mój baner', 'fields' => [['type' => 'text', 'name' => 'heading', 'label' => 'H']]]),
            'blocks/hero-banner/render.blade.php' => '<b>{{ $attrs["heading"] }}</b>',
        ]);
        Content::create([
            'content_type_id' => ContentType::where('key', 'page')->value('id'), 'title' => 'Start', 'slug' => 'start',
            'status' => 'published', 'published_at' => now()->subDay(), 'fields' => [], 'html_cache' => '<p>x</p>',
        ]);

        $themes = app(ThemeManager::class);
        $themes->activate('child-theme');
        $active = $themes->active();

        $this->assertSame(['child-theme', 'lumin-default'], array_column($active->lineage(), 'slug'));
        $this->assertSame(['default', 'landing', 'wide'], array_column($active->templates(), 'key'));
        $primary = collect($active->tokens())->firstWhere('key', 'primary');
        $this->assertSame('#ff0000', $primary['default']);
        $this->assertContains('accent', array_column($active->tokens(), 'key'));
        $this->assertContains('radius', array_column($active->tokens(), 'key'));
        $this->assertContains('landing', array_column($themes->templatesFor(ContentType::where('key', 'page')->first()), 'key'));

        // Widok z motywu potomnego, layout i style z rodzica.
        $this->get('/start')->assertOk()->assertSee('Potomny: Start')->assertSee('lm-site-header', false)->assertSee('theme-assets/lumin-default/menu.css', false)->assertSee('theme-assets/lumin-default/theme.css', false);
        $this->assertStringContainsString('theme-assets/child-theme/child.css', $active->asset('child.css'));

        // Bloki: nadpisany z dziecka, pozostałe od rodzica.
        $blocks = app(BlockRegistry::class);
        $this->assertSame(['hero-banner', 'testimonials'], array_keys($blocks->all()));
        $this->assertSame('Mój baner', $blocks->get('hero-banner')['label']);
        $this->assertSame('<b>X</b>', $blocks->render('hero-banner', ['heading' => 'X']));

        // Ustawienia Dostosuj są osobne dla motywu potomnego.
        $this->assertSame([], app(Customizer::class)->saved());
    }

    public function test_missing_parent_and_cycles_block_activation(): void
    {
        $this->theme('orphan', ['name' => 'O', 'parent' => 'nie-ma']);
        $this->theme('cyc-a', ['name' => 'A', 'parent' => 'cyc-b']);
        $this->theme('cyc-b', ['name' => 'B', 'parent' => 'cyc-a']);

        $themes = app(ThemeManager::class);
        $this->assertSame('missing_parent', $themes->all()['orphan']->error);
        $this->assertSame('cycle', $themes->all()['cyc-a']->error);

        $this->actingAs($this->admin());
        $this->post('/cms/themes/orphan/activate')->assertSessionHasErrors('theme');
        $this->post('/cms/themes/nie-ma/activate')->assertSessionHasErrors('theme');
        $this->assertSame('lumin-default', $themes->active()->slug);
    }

    public function test_themes_panel_lists_activates_and_is_admin_only(): void
    {
        $this->theme('inny', ['name' => 'Inny', 'version' => '2.0', 'parent' => 'lumin-default']);
        $this->actingAs($this->admin());

        $this->get('/cms/themes')->assertOk()->assertInertia(fn ($p) => $p
            ->component('themes/index')->has('themes', 2)->where('themes.0.slug', 'lumin-default')->where('themes.0.active', true)
            ->where('themes.0.can_delete', false)->where('themes.1.parent', 'lumin-default')->where('themes.1.can_delete', true)
            ->where('uploadsEnabled', true));

        $this->post('/cms/themes/inny/activate')->assertRedirect();
        $this->assertSame('inny', app(ThemeManager::class)->active()->slug);
        $this->get('/cms/themes')->assertInertia(fn ($p) => $p->where('themes.0.slug', 'inny')->where('themes.1.can_delete', false));

        $this->actingAs(User::factory()->role('editor')->create());
        $this->get('/cms/themes')->assertForbidden();
        $this->post('/cms/themes/inny/activate')->assertForbidden();
    }

    public function test_valid_zip_is_installed_then_exported_and_deleted(): void
    {
        $this->actingAs($this->admin());

        $this->post('/cms/themes', ['file' => $this->zip($this->validFiles() + ['mini/screenshot.png' => 'PNGDATA'])])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFileExists(base_path('themes/mini/views/layout.blade.php'));
        $this->assertSame('Mini', app(ThemeManager::class)->all()['mini']->name());
        $this->get('/cms/themes/mini/screenshot')->assertOk();
        $this->get('/cms/themes')->assertInertia(fn ($p) => $p->where('themes.1.version', '1.2.0')->where('themes.1.has_screenshot', true));

        $response = $this->get('/cms/themes/mini/export');
        $response->assertOk()->assertHeader('content-disposition', 'attachment; filename=mini.zip');
        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $this->assertContains('mini/theme.json', $names);
        $this->assertContains('mini/views/layout.blade.php', $names);

        $this->delete('/cms/themes/mini')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDirectoryDoesNotExist(base_path('themes/mini'));
    }

    public function test_zip_without_root_folder_uses_the_file_name_as_slug(): void
    {
        $this->actingAs($this->admin());

        $this->post('/cms/themes', ['file' => $this->zip($this->validFiles(''), 'Moj Motyw.zip')])->assertSessionHasNoErrors();

        $this->assertDirectoryExists(base_path('themes/moj-motyw'));
    }

    public function test_child_theme_zip_needs_an_installed_parent_but_no_layout(): void
    {
        $this->actingAs($this->admin());
        $child = ['dziecko/theme.json' => json_encode(['name' => 'Dziecko', 'parent' => 'lumin-default']), 'dziecko/assets/theme.css' => 'a{}'];

        $this->post('/cms/themes', ['file' => $this->zip($child, 'dziecko.zip')])->assertSessionHasNoErrors();
        $this->assertSame('lumin-default', app(ThemeManager::class)->all()['dziecko']->parent->slug);

        $bad = ['sierota/theme.json' => json_encode(['name' => 'S', 'parent' => 'nie-ma'])];
        $this->post('/cms/themes', ['file' => $this->zip($bad, 'sierota.zip')])->assertSessionHasErrors('file');
        $this->assertDirectoryDoesNotExist(base_path('themes/sierota'));
    }

    public function test_dangerous_or_invalid_archives_are_rejected_without_writing_anything(): void
    {
        $this->actingAs($this->admin());
        $before = glob(base_path('themes/*'));

        $cases = [
            'zip slip' => $this->validFiles() + ['mini/../../evil.blade.php' => 'x'],
            'plain php' => $this->validFiles() + ['mini/views/shell.php' => '<?php system($_GET[0]);'],
            'hidden file' => $this->validFiles() + ['mini/.htaccess' => 'x'],
            'absolute path' => $this->validFiles() + ['/etc/x.css' => 'x'],
            'no theme.json' => ['mini/views/layout.blade.php' => 'x'],
            'broken manifest' => ['mini/theme.json' => '{oops', 'mini/views/layout.blade.php' => 'x'],
            'no name' => ['mini/theme.json' => '{}', 'mini/views/layout.blade.php' => 'x'],
            'no layout' => ['mini/theme.json' => json_encode(['name' => 'M']), 'mini/assets/a.css' => 'x'],
            'two roots' => ['a/theme.json' => '{"name":"A"}', 'b/views/layout.blade.php' => 'x'],
            'bad slug' => $this->validFiles('Zły Motyw/'),
        ];

        foreach ($cases as $label => $files) {
            $this->post('/cms/themes', ['file' => $this->zip($files)])->assertSessionHasErrors('file');
        }

        $this->post('/cms/themes', ['file' => UploadedFile::fake()->createWithContent('x.zip', 'to nie jest zip')])->assertSessionHasErrors('file');
        $this->assertSame($before, glob(base_path('themes/*')));
        $this->assertSame([], glob(base_path('themes/.install-*')) ?: []);
    }

    public function test_duplicate_slug_is_rejected_and_uploads_can_be_disabled(): void
    {
        $this->actingAs($this->admin());
        $this->post('/cms/themes', ['file' => $this->zip($this->validFiles())])->assertSessionHasNoErrors();
        $this->post('/cms/themes', ['file' => $this->zip($this->validFiles())])->assertSessionHasErrors('file');

        config(['cms.theme_uploads' => false]);
        $this->post('/cms/themes', ['file' => $this->zip($this->validFiles('inny/'))])->assertForbidden();
        $this->get('/cms/themes')->assertInertia(fn ($p) => $p->where('uploadsEnabled', false));
    }

    public function test_delete_rules(): void
    {
        $this->theme('rodzic', ['name' => 'R', 'parent' => 'lumin-default']);
        $this->theme('dziecko', ['name' => 'D', 'parent' => 'rodzic']);
        $this->actingAs($this->admin());

        $this->delete('/cms/themes/lumin-default')->assertSessionHasErrors('theme');
        $this->delete('/cms/themes/rodzic')->assertSessionHasErrors('theme');
        $this->assertDirectoryExists(base_path('themes/rodzic'));

        app(ThemeManager::class)->activate('dziecko');
        $this->delete('/cms/themes/dziecko')->assertSessionHasErrors('theme');
        $this->delete('/cms/themes/nie-ma')->assertSessionHasErrors('theme');

        app(ThemeManager::class)->activate('lumin-default');
        $this->delete('/cms/themes/dziecko')->assertSessionHasNoErrors();
        $this->delete('/cms/themes/rodzic')->assertSessionHasNoErrors();
        $this->assertDirectoryDoesNotExist(base_path('themes/rodzic'));
    }
}
