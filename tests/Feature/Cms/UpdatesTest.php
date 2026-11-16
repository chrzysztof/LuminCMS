<?php

namespace Tests\Feature\Cms;

use App\Cms\Updates\Release;
use App\Cms\Updates\UpdateChecker;
use App\Cms\Updates\UpdateException;
use App\Cms\Updates\Updater;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class UpdatesTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CmsSeeder::class);
        config(['cms.version' => '0.2.0', 'cms.update_checks' => true, 'cms.self_update' => true, 'cms.update_repo' => 'acme/lumin']);
        Cache::flush();
        $this->tmp = sys_get_temp_dir().'/lumin-upd-'.uniqid();
        File::ensureDirectoryExists($this->tmp);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    private function fakeGithub(string $tag = 'v0.3.0', ?string $digest = null): void
    {
        Http::fake(['api.github.com/*' => Http::response([
            'tag_name' => $tag, 'name' => "Lumin CMS {$tag}", 'body' => 'Nowości', 'html_url' => 'https://github.com/acme/lumin/releases/tag/'.$tag,
            'published_at' => '2026-11-16T10:00:00Z',
            'assets' => [[
                'name' => 'lumin-cms-'.ltrim($tag, 'v').'.zip', 'size' => 1234, 'digest' => $digest ?? 'sha256:'.str_repeat('a', 64),
                'browser_download_url' => 'https://github.com/acme/lumin/releases/download/'.$tag.'/lumin-cms-'.ltrim($tag, 'v').'.zip',
            ]],
        ])]);
    }

    public function test_detects_newer_release_and_caches_it(): void
    {
        $this->fakeGithub();
        $checker = app(UpdateChecker::class);

        $this->assertSame('0.3.0', $checker->latest()?->version);
        $this->assertTrue($checker->updateAvailable());

        $checker->latest(); // druga próba bierze z cache
        Http::assertSentCount(1);
    }

    public function test_same_or_older_version_is_not_an_update(): void
    {
        $this->fakeGithub('v0.2.0');
        app(UpdateChecker::class)->refresh();

        $this->assertFalse(app(UpdateChecker::class)->updateAvailable());
        $this->assertTrue(UpdateChecker::newer('0.10.0', '0.9.0'));
        $this->assertFalse(UpdateChecker::newer('v0.2.0', '0.2.0'));
    }

    public function test_network_failure_keeps_last_known_release(): void
    {
        $ok = ['tag_name' => 'v0.3.0', 'name' => 'x', 'body' => '', 'assets' => []];
        Http::fake(['api.github.com/*' => Http::sequence()->push($ok)->push('', 500)]);
        app(UpdateChecker::class)->refresh();
        app(UpdateChecker::class)->refresh();

        $this->assertTrue(app(UpdateChecker::class)->updateAvailable());
        $this->assertNotNull(app(UpdateChecker::class)->state()['error']);
    }

    public function test_disabled_checks_make_no_requests(): void
    {
        config(['cms.update_checks' => false]);
        Http::fake();

        $this->assertNull(app(UpdateChecker::class)->latest(force: true));
        Http::assertNothingSent();
    }

    public function test_release_without_checksum_is_not_installable(): void
    {
        $this->fakeGithub(digest: 'brak');
        $this->assertFalse(app(UpdateChecker::class)->refresh()->installable());
    }

    public function test_only_admins_see_updates_page_and_banner(): void
    {
        $this->fakeGithub();
        app(UpdateChecker::class)->refresh();
        $admin = User::factory()->create(['role' => 'administrator']);
        $editor = User::factory()->create(['role' => 'editor']);

        $this->actingAs($editor)->get('/cms/updates')->assertForbidden();
        $this->actingAs($editor)->post('/cms/updates/install')->assertForbidden();

        $this->actingAs($admin)->get('/cms/updates')->assertInertia(fn (Assert $p) => $p
            ->component('updates/index')->where('available', true)->where('release.version', '0.3.0')
            ->where('cmsUpdate.version', '0.3.0'));
        $this->actingAs($editor)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('cmsUpdate', null));
    }

    public function test_check_button_refreshes_status(): void
    {
        $this->fakeGithub();
        $this->actingAs(User::factory()->create(['role' => 'administrator']))->post('/cms/updates/check')->assertRedirect();

        $this->assertTrue(app(UpdateChecker::class)->updateAvailable());
    }

    // --- instalator ---

    /** Buduje katalog "aplikacji" ze starą wersją i paczkę ZIP z nową. @return array{0: Updater, 1: Release, 2: string, 3: list<string>} */
    private function scenario(string $version = '0.3.0', bool $badZip = false, bool $failMigrate = false): array
    {
        $app = $this->tmp.'/app';
        foreach (['app', 'vendor', 'config', 'storage', 'themes/custom', 'themes/lumin-default', 'public', 'database'] as $dir) {
            File::ensureDirectoryExists("{$app}/{$dir}");
        }
        file_put_contents("{$app}/app/Old.php", 'stara');
        file_put_contents("{$app}/.env", 'APP_KEY=zachowaj');
        file_put_contents("{$app}/storage/dane.txt", 'dane');
        file_put_contents("{$app}/themes/custom/own.txt", 'moj motyw');
        file_put_contents("{$app}/themes/lumin-default/v.txt", 'stary');
        file_put_contents("{$app}/database/database.sqlite", 'baza');
        file_put_contents("{$app}/public/storage-marker.txt", 'x');

        $zipPath = $this->tmp.'/release.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $root = "lumin-cms-{$version}";
        $zip->addFromString("{$root}/artisan", '<?php');
        $zip->addFromString("{$root}/VERSION", $version."\n");
        $zip->addFromString("{$root}/bootstrap/app.php", '<?php');
        $zip->addFromString("{$root}/vendor/autoload.php", '<?php');
        $zip->addFromString("{$root}/app/New.php", 'nowa');
        $zip->addFromString("{$root}/themes/lumin-default/v.txt", 'nowy');
        $zip->addFromString("{$root}/database/migrations/x.php", 'm');
        if ($badZip) {
            $zip->addFromString("{$root}/../../evil.php", 'zly');
        }
        $zip->close();

        $calls = [];
        $updater = (new Updater($app, function (string $cmd) use (&$calls, $failMigrate) {
            $calls[] = $cmd;
            if ($failMigrate && str_starts_with($cmd, 'migrate')) {
                throw new UpdateException('migracja padla');
            }
        }));
        $this->app->instance('test.calls', fn () => $calls);

        $release = new Release($version, "Lumin {$version}", '', 'https://github.com/acme/lumin/releases/tag/v'.$version, null,
            "lumin-cms-{$version}.zip", "https://github.com/acme/lumin/releases/download/v{$version}/lumin-cms-{$version}.zip",
            1, hash_file('sha256', $zipPath), null);

        Http::fake(['github.com/*' => Http::response(file_get_contents($zipPath))]);
        // Http::sink zapisuje do pliku, więc fake musi podać treść; sink obsługuje to w teście przez odpowiedź z ciałem.

        return [$updater, $release, $app, $calls];
    }

    public function test_install_replaces_code_and_keeps_user_data(): void
    {
        [$updater, $release, $app] = $this->scenario();
        $log = [];
        $updater->logUsing(function ($m) use (&$log) {
            $log[] = $m;
        })->install($release);

        $this->assertFileExists("{$app}/app/New.php");
        $this->assertFileDoesNotExist("{$app}/app/Old.php");
        $this->assertSame('nowy', file_get_contents("{$app}/themes/lumin-default/v.txt"));
        $this->assertSame('moj motyw', file_get_contents("{$app}/themes/custom/own.txt"));
        $this->assertSame('APP_KEY=zachowaj', file_get_contents("{$app}/.env"));
        $this->assertSame('dane', file_get_contents("{$app}/storage/dane.txt"));
        $this->assertSame('baza', file_get_contents("{$app}/database/database.sqlite"));
        $this->assertSame('0.3.0', trim(file_get_contents("{$app}/VERSION")));
        $this->assertNotEmpty($log);
    }

    public function test_wrong_checksum_aborts_before_touching_files(): void
    {
        [$updater, $release, $app] = $this->scenario();
        $bad = Release::fromArray([...$release->toArray(), 'sha256' => str_repeat('0', 64)]);

        try {
            $updater->install($bad);
            $this->fail('Powinno rzucić wyjątek');
        } catch (UpdateException $e) {
            $this->assertSame(__('Suma kontrolna paczki nie zgadza się. Instalacja przerwana.'), $e->getMessage());
        }
        $this->assertFileExists("{$app}/app/Old.php");
        $this->assertFileDoesNotExist("{$app}/app/New.php");
    }

    public function test_zip_slip_is_rejected(): void
    {
        [$updater, $release, $app] = $this->scenario(badZip: true);

        $this->expectException(UpdateException::class);
        try {
            $updater->install($release);
        } finally {
            $this->assertFileExists("{$app}/app/Old.php");
            $this->assertFileDoesNotExist($this->tmp.'/evil.php');
        }
    }

    public function test_failed_migration_restores_previous_version(): void
    {
        [$updater, $release, $app] = $this->scenario(failMigrate: true);

        try {
            $updater->install($release);
            $this->fail('Powinno rzucić wyjątek');
        } catch (UpdateException $e) {
            $this->assertSame('migracja padla', $e->getMessage());
        }

        $this->assertSame('stara', file_get_contents("{$app}/app/Old.php"));
        $this->assertFileDoesNotExist("{$app}/app/New.php");
        $this->assertSame('stary', file_get_contents("{$app}/themes/lumin-default/v.txt"));
        $this->assertSame('APP_KEY=zachowaj', file_get_contents("{$app}/.env"));
    }

    public function test_download_must_come_from_release_repository(): void
    {
        [$updater, $release] = $this->scenario();
        $evil = Release::fromArray([...$release->toArray(), 'assetUrl' => 'https://evil.example/lumin.zip']);

        $this->expectException(UpdateException::class);
        $updater->install($evil);
    }

    public function test_git_checkout_and_disabled_mode_block_self_update(): void
    {
        [$updater, , $app] = $this->scenario();
        $this->assertNull($updater->blocker());

        File::ensureDirectoryExists("{$app}/.git");
        $this->assertSame(__('To repozytorium git. Zaktualizuj kod poleceniem git pull i composer install.'), $updater->blocker());

        File::deleteDirectory("{$app}/.git");
        config(['cms.self_update' => false]);
        $this->assertNotNull($updater->blocker());
    }
}
