<?php

namespace App\Cms\Updates;

use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Instaluje wydanie z GitHub Releases: pobranie, weryfikacja sumy SHA-256, rozpakowanie do katalogu tymczasowego,
 * tryb konserwacji, podmiana plików z kopią zapasową, migracje i czyszczenie cache. Błąd po podmianie plików
 * przywraca poprzednią wersję. Dane (.env, storage, baza, własne motywy, wgrane pliki) nie są ruszane.
 */
class Updater
{
    /** Ścieżki, których aktualizacja nigdy nie zastępuje. */
    private const PRESERVE = ['.env', '.git', 'storage', 'VERSION.local'];

    /** Katalogi scalane element po elemencie (zawierają też dane użytkownika: własne motywy, bazę SQLite, link storage). */
    private const MERGE = ['themes', 'public', 'database'];

    /** @var Closure(string): void */
    private Closure $artisan;

    /** @var Closure(string): void */
    private Closure $log;

    /**
     * @param  Closure(string): void|null  $artisan  Wykonuje polecenie artisan ("down", "migrate --force"...); w testach podmieniany.
     */
    public function __construct(private readonly ?string $base = null, ?Closure $artisan = null)
    {
        $this->artisan = $artisan ?? Closure::fromCallable([$this, 'runArtisan']);
        $this->log = static fn (string $m) => null;
    }

    public function base(): string
    {
        return rtrim($this->base ?? base_path(), '/');
    }

    /** Powód, dla którego aktualizacja z panelu jest niemożliwa (null = można). */
    public function blocker(): ?string
    {
        if (! config('cms.self_update')) {
            return __('Aktualizacja z panelu jest wyłączona (CMS_SELF_UPDATE=false).');
        }
        if (is_dir($this->base().'/.git')) {
            return __('To repozytorium git. Zaktualizuj kod poleceniem git pull i composer install.');
        }
        if (! class_exists(ZipArchive::class)) {
            return __('Brakuje rozszerzenia PHP zip.');
        }
        foreach (['', '/app', '/vendor', '/config', '/storage'] as $dir) {
            if (! is_writable($this->base().$dir)) {
                return __('Brak uprawnień zapisu do katalogu :dir.', ['dir' => $dir === '' ? '/' : $dir]);
            }
        }

        return null;
    }

    public function logUsing(Closure $log): static
    {
        $this->log = $log;

        return $this;
    }

    /** Instaluje podane wydanie. Rzuca UpdateException z czytelnym komunikatem. */
    public function install(Release $release): void
    {
        if ($blocker = $this->blocker()) {
            throw new UpdateException($blocker);
        }
        if (! $release->installable()) {
            throw new UpdateException(__('To wydanie nie ma paczki z sumą kontrolną, więc nie można go zainstalować automatycznie.'));
        }
        if (! UpdateChecker::newer($release->version, (string) config('cms.version'))) {
            throw new UpdateException(__('Masz już tę wersję lub nowszą.'));
        }

        @set_time_limit(0);
        @ignore_user_abort(true);

        $work = $this->base().'/storage/app/updates';
        $zip = "{$work}/lumin-cms-{$release->version}.zip";
        $staging = "{$work}/staging";
        $backup = "{$work}/backup-".config('cms.version').'-'.date('YmdHis');
        File::ensureDirectoryExists($work);
        File::deleteDirectory($staging);

        try {
            $this->say(__('Pobieranie paczki...'));
            $this->download($release, $zip);
            $this->say(__('Weryfikacja sumy kontrolnej...'));
            $this->verify($release, $zip);
            $this->say(__('Rozpakowywanie...'));
            $root = $this->extract($release, $zip, $staging);
        } catch (Throwable $e) {
            File::deleteDirectory($staging);
            File::delete($zip);

            throw $e instanceof UpdateException ? $e : new UpdateException($e->getMessage(), 0, $e);
        }

        $moved = [];
        $this->say(__('Włączanie trybu konserwacji...'));
        ($this->artisan)('down --retry=60');

        try {
            $this->say(__('Podmiana plików (kopia zapasowa w storage/app/updates)...'));
            File::ensureDirectoryExists($backup);
            $this->swap($root, $backup, $moved);
            $this->say(__('Migracje bazy danych...'));
            ($this->artisan)('migrate --force');
            ($this->artisan)('optimize:clear');
        } catch (Throwable $e) {
            $this->say(__('Błąd: przywracanie poprzedniej wersji...'));
            $this->rollback($backup, $moved);
            ($this->artisan)('optimize:clear');
            ($this->artisan)('up');

            throw $e instanceof UpdateException ? $e : new UpdateException($e->getMessage(), 0, $e);
        } finally {
            File::deleteDirectory($staging);
        }

        $this->resetOpcache();
        ($this->artisan)('up');
        File::delete($zip);
        $this->pruneBackups($work, $backup);
        $this->say(__('Zaktualizowano do wersji :v.', ['v' => $release->version]));
    }

    private function say(string $message): void
    {
        ($this->log)($message);
    }

    private function download(Release $release, string $target): void
    {
        $repo = (string) config('cms.update_repo');
        if (! str_starts_with((string) $release->assetUrl, "https://github.com/{$repo}/releases/download/")) {
            throw new UpdateException(__('Adres paczki nie pochodzi z repozytorium wydań.'));
        }

        $response = Http::withHeaders(['User-Agent' => 'LuminCMS/'.config('cms.version')])
            ->timeout(600)->withOptions(['sink' => $target])->get($release->assetUrl);

        if (! $response->successful() || ! is_file($target)) {
            throw new UpdateException(__('Nie udało się pobrać paczki (kod :code).', ['code' => $response->status()]));
        }
    }

    private function verify(Release $release, string $file): void
    {
        $expected = $release->sha256;

        if ($expected === null && $release->checksumUrl !== null) {
            $repo = (string) config('cms.update_repo');
            if (! str_starts_with($release->checksumUrl, "https://github.com/{$repo}/releases/download/")) {
                throw new UpdateException(__('Adres sumy kontrolnej nie pochodzi z repozytorium wydań.'));
            }
            $body = Http::timeout(30)->get($release->checksumUrl)->body();
            $expected = preg_match('/\b([a-f0-9]{64})\b/i', $body, $m) ? strtolower($m[1]) : null;
        }

        if ($expected === null || ! hash_equals($expected, hash_file('sha256', $file))) {
            throw new UpdateException(__('Suma kontrolna paczki nie zgadza się. Instalacja przerwana.'));
        }
    }

    /** Rozpakowuje paczkę bezpiecznie (bez wyjścia poza katalog) i zwraca katalog z kodem. */
    private function extract(Release $release, string $zipPath, string $staging): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new UpdateException(__('Paczka jest uszkodzona.'));
        }

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, '\\') || preg_match('/^[a-zA-Z]:/', $name)) {
                $zip->close();
                throw new UpdateException(__('Paczka zawiera niedozwoloną ścieżkę.'));
            }
            $names[] = $name;
        }

        File::ensureDirectoryExists($staging);
        if (! $zip->extractTo($staging)) {
            $zip->close();
            throw new UpdateException(__('Nie udało się rozpakować paczki.'));
        }
        $zip->close();

        // Paczka ma jeden katalog główny (lumin-cms-<wersja>/).
        $dirs = glob($staging.'/*', GLOB_ONLYDIR) ?: [];
        $root = count($dirs) === 1 && is_file($dirs[0].'/artisan') ? $dirs[0] : $staging;

        foreach (['artisan', 'VERSION', 'bootstrap/app.php', 'vendor/autoload.php'] as $required) {
            if (! is_file("{$root}/{$required}")) {
                throw new UpdateException(__('Paczka nie wygląda na wydanie Lumin CMS (brak :file).', ['file' => $required]));
            }
        }
        if (trim((string) file_get_contents("{$root}/VERSION")) !== $release->version) {
            throw new UpdateException(__('Wersja w paczce nie zgadza się z wydaniem.'));
        }

        return $root;
    }

    /**
     * Podmienia pliki: każdy element najwyższego poziomu (a w katalogach scalanych - jego dzieci) trafia do kopii,
     * a na jego miejsce przenoszona jest nowa wersja. Przenoszenie w obrębie jednego dysku jest szybkie i atomowe.
     *
     * @param  array<int, array{0: string, 1: string}>  $moved  Lista [ścieżka docelowa, ścieżka kopii lub '' gdy nie istniała].
     */
    private function swap(string $root, string $backup, array &$moved): void
    {
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, self::PRESERVE, true)) {
                continue;
            }

            if (in_array($entry, self::MERGE, true) && is_dir("{$root}/{$entry}") && is_dir("{$this->base()}/{$entry}")) {
                foreach (scandir("{$root}/{$entry}") ?: [] as $child) {
                    if ($child !== '.' && $child !== '..' && ! ($entry === 'public' && $child === 'storage')) {
                        $this->replace("{$entry}/{$child}", $root, $backup, $moved);
                    }
                }

                continue;
            }

            $this->replace($entry, $root, $backup, $moved);
        }
    }

    private function replace(string $relative, string $root, string $backup, array &$moved): void
    {
        $target = "{$this->base()}/{$relative}";
        $saved = '';

        if (file_exists($target) || is_link($target)) {
            $saved = "{$backup}/{$relative}";
            File::ensureDirectoryExists(dirname($saved));
            if (! @rename($target, $saved)) {
                throw new UpdateException(__('Nie udało się przenieść :path do kopii zapasowej.', ['path' => $relative]));
            }
        }

        $moved[] = [$relative, $saved];
        File::ensureDirectoryExists(dirname($target));
        if (! @rename("{$root}/{$relative}", $target)) {
            throw new UpdateException(__('Nie udało się zapisać :path.', ['path' => $relative]));
        }
    }

    /** @param  array<int, array{0: string, 1: string}>  $moved */
    private function rollback(string $backup, array $moved): void
    {
        foreach (array_reverse($moved) as [$relative, $saved]) {
            $target = "{$this->base()}/{$relative}";
            if (is_dir($target) && ! is_link($target)) {
                File::deleteDirectory($target);
            } elseif (file_exists($target) || is_link($target)) {
                @unlink($target);
            }
            if ($saved !== '' && (file_exists($saved) || is_link($saved))) {
                @rename($saved, $target);
            }
        }
        File::deleteDirectory($backup);
    }

    /** Zostawia tylko najnowszą kopię zapasową. */
    private function pruneBackups(string $work, string $keep): void
    {
        foreach (glob("{$work}/backup-*", GLOB_ONLYDIR) ?: [] as $dir) {
            if ($dir !== $keep) {
                File::deleteDirectory($dir);
            }
        }
    }

    private function resetOpcache(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** Uruchamia artisan w osobnym procesie, bo kod aplikacji w tym procesie jest już podmieniony. */
    private function runArtisan(string $command): void
    {
        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
        $process = Process::fromShellCommandline(escapeshellarg($php).' artisan '.$command.' --no-interaction', $this->base(), null, null, 600);
        $process->run();

        if (! $process->isSuccessful() && ! str_starts_with($command, 'up') && ! str_starts_with($command, 'down')) {
            throw new UpdateException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: __('Polecenie :c nie powiodło się.', ['c' => $command]));
        }
    }
}
