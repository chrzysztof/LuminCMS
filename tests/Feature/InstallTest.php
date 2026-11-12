<?php

namespace Tests\Feature;

use App\Cms\Content\Content;
use App\Cms\Install\EnvFile;
use App\Cms\Install\Installer;
use App\Cms\Install\InstallState;
use App\Cms\Install\Requirements;
use App\Cms\Menus\Menu;
use App\Cms\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstallTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/lumin-install-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir.'/.env', "APP_NAME=Laravel\nAPP_ENV=local\nAPP_DEBUG=true\n# DB_HOST=127.0.0.1\nSESSION_DRIVER=database\n");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/{.env,*}', GLOB_BRACE) ?: []);
        @rmdir($this->dir);

        parent::tearDown();
    }

    /** Stan „przed instalacją”: pusta baza w pamięci, plik blokady i .env w katalogu tymczasowym. */
    private function fresh(): void
    {
        config(['cms.installed' => null, 'cms.install_lock' => $this->dir.'/installed']);
        $this->app->bind(EnvFile::class, fn () => new EnvFile($this->dir.'/.env'));
        $this->app->forgetInstance(Settings::class);
    }

    private function site(array $over = []): array
    {
        return $over + [
            'site_name' => 'Moja strona', 'url' => 'https://example.test', 'locale' => 'pl',
            'admin_name' => 'Ada', 'admin_email' => 'ada@example.test',
            'admin_password' => 'tajne-haslo-1', 'admin_password_confirmation' => 'tajne-haslo-1', 'starter' => '1',
        ];
    }

    public function test_installed_site_hides_the_installer(): void
    {
        $this->artisan('migrate', ['--force' => true]);
        $this->get('/install')->assertNotFound();
        $this->post('/install/site', $this->site())->assertNotFound();
    }

    public function test_fresh_site_redirects_everything_to_the_installer(): void
    {
        $this->fresh();

        $this->get('/')->assertRedirect('/install');
        $this->get('/cms')->assertRedirect('/install');
        $this->get('/some/page')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Lumin CMS')->assertSee('PHP');
        $this->assertSame('file', config('session.driver'));
    }

    public function test_installer_language_is_chosen_with_a_cookie(): void
    {
        $this->fresh();

        $this->post('/install/language', ['locale' => 'de'])->assertCookie('lumin_locale');
        $this->post('/install/language', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_requirements_pass_in_the_test_environment(): void
    {
        $r = app(Requirements::class);

        $this->assertTrue($r->passes());
        $this->assertArrayHasKey('sqlite', $r->drivers());
        $this->assertContains('php', array_column($r->checks(), 'key'));
    }

    public function test_wrong_database_credentials_show_an_error_and_write_nothing(): void
    {
        $this->fresh();
        $before = file_get_contents($this->dir.'/.env');

        if (! array_key_exists('mysql', app(Requirements::class)->drivers())) {
            $this->markTestSkipped('pdo_mysql is not available');
        }

        $this->from('/install/database')->post('/install/database', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x',
        ])->assertRedirect('/install/database')->assertSessionHasErrors('connection');

        $this->assertSame($before, file_get_contents($this->dir.'/.env'));
    }

    public function test_database_form_validates_required_server_fields(): void
    {
        $this->fresh();

        $this->post('/install/database', ['driver' => 'mysql'])->assertSessionHasErrors(['host', 'port', 'database', 'username']);
        $this->post('/install/database', ['driver' => 'oracle'])->assertSessionHasErrors('driver');
    }

    public function test_site_form_validates_input(): void
    {
        $this->fresh();

        $this->post('/install/site', $this->site(['admin_password' => 'short', 'admin_password_confirmation' => 'short']))->assertSessionHasErrors('admin_password');
        $this->post('/install/site', $this->site(['admin_password_confirmation' => 'inne']))->assertSessionHasErrors('admin_password');
        $this->post('/install/site', $this->site(['admin_email' => 'nie-email']))->assertSessionHasErrors('admin_email');
        $this->post('/install/site', $this->site(['url' => 'ftp://x']))->assertSessionHasErrors('url');
        $this->post('/install/site', $this->site(['locale' => 'xx']))->assertSessionHasErrors('locale');
        $this->assertFalse(is_file($this->dir.'/installed'));
    }

    public function test_full_installation_creates_everything(): void
    {
        $this->fresh();

        $this->post('/install/site', $this->site())->assertOk()->assertSee('ada@example.test');

        $admin = User::where('email', 'ada@example.test')->firstOrFail();
        $this->assertSame('administrator', $admin->role);
        $this->assertSame('pl', $admin->locale);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(password_verify('tajne-haslo-1', $admin->password));

        $this->assertSame('Moja strona', app(Settings::class)->get('seo', 'site_name'));
        $this->assertTrue(Schema::hasTable('contents'));
        $this->assertSame(1, DB::table('content_types')->where('key', 'page')->count());

        $this->assertTrue(Content::where('slug', 'home')->where('status', 'published')->exists());
        $this->assertSame('Strona główna', Content::where('slug', 'home')->value('title'));
        $this->assertTrue(Content::where('slug', 'hello-world')->exists());
        $this->assertSame(2, Menu::where('location', 'primary')->firstOrFail()->items()->count());

        $env = new EnvFile($this->dir.'/.env');
        $this->assertSame('Moja strona', $env->get('APP_NAME'));
        $this->assertSame('https://example.test', $env->get('APP_URL'));
        $this->assertSame('production', $env->get('APP_ENV'));
        $this->assertSame('false', $env->get('APP_DEBUG'));
        $this->assertSame('database', $env->get('SESSION_DRIVER'));
        $this->assertSame('database', $env->get('CACHE_STORE'));

        $this->assertTrue(InstallState::installed());
        $this->get('/install')->assertNotFound();
    }

    public function test_installation_without_starter_content_and_a_second_run_is_refused(): void
    {
        $this->fresh();

        $this->post('/install/site', $this->site(['starter' => '0']))->assertOk();

        $this->assertSame(0, Content::count());
        $this->assertSame(0, Menu::count());

        $this->expectExceptionMessage('already installed');
        app(Installer::class)->install($this->site());
    }

    public function test_existing_users_mark_a_legacy_site_as_installed(): void
    {
        $this->fresh();
        $this->artisan('migrate', ['--force' => true]);
        $this->assertFalse(InstallState::installed());

        User::factory()->create();

        $this->assertTrue(InstallState::installed());
        $this->assertFileExists($this->dir.'/installed');
    }

    public function test_cli_refuses_when_already_installed(): void
    {
        $this->artisan('cms:install', ['--no-interaction' => true])->assertFailed();
    }

    public function test_env_file_replaces_comments_out_quotes_and_appends(): void
    {
        $env = new EnvFile($this->dir.'/.env');
        $env->set(['APP_NAME' => 'My Site #1', 'DB_HOST' => '10.0.0.5', 'SESSION_DRIVER' => null, 'NEW_KEY' => 'a$b', 'APP_DEBUG' => false]);

        $text = file_get_contents($this->dir.'/.env');
        $this->assertStringContainsString('APP_NAME="My Site #1"', $text);
        $this->assertStringContainsString("\nDB_HOST=10.0.0.5\n", $text);
        $this->assertStringContainsString('# SESSION_DRIVER=', $text);
        $this->assertStringContainsString('NEW_KEY="a\$b"', $text);
        $this->assertStringContainsString('APP_DEBUG=false', $text);
        $this->assertSame('My Site #1', $env->get('APP_NAME'));
        $this->assertSame('local', $env->get('APP_ENV'));
        $this->assertSame(1, substr_count($text, 'DB_HOST'));
    }
}
