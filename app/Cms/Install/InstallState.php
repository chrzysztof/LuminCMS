<?php

namespace App\Cms\Install;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Czy CMS jest zainstalowany? Źródłem prawdy jest plik blokady storage/app/installed.
 * Starsze instalacje (konta w bazie, ale bez pliku) są wykrywane i dostają plik automatycznie.
 */
class InstallState
{
    public static function lockPath(): string
    {
        return (string) config('cms.install_lock', storage_path('app/installed'));
    }

    public static function installed(): bool
    {
        $forced = config('cms.installed');

        if ($forced !== null) {
            return (bool) $forced;
        }

        if (is_file(self::lockPath())) {
            return true;
        }

        try {
            if (Schema::hasTable('users') && DB::table('users')->exists()) {
                self::markInstalled();

                return true;
            }
        } catch (Throwable) {
            // Brak bazy albo połączenia: instalacja jeszcze się nie odbyła.
        }

        return false;
    }

    public static function markInstalled(): void
    {
        $dir = dirname(self::lockPath());

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(self::lockPath(), json_encode(['installed_at' => date('c')]));
    }

    /**
     * Wywoływane na samym początku żądania WWW (bootstrap/app.php): tworzy .env z .env.example
     * i klucz aplikacji, żeby świeżo rozpakowana paczka uruchomiła się bez żadnej konfiguracji.
     */
    public static function prepareEnvironment(string $basePath): void
    {
        if (PHP_SAPI === 'cli' || is_file($basePath.'/storage/app/installed')) {
            return;
        }

        $env = new EnvFile($basePath.'/.env');

        if (! $env->exists() && is_file($basePath.'/.env.example')) {
            @copy($basePath.'/.env.example', $basePath.'/.env');
        }

        if ($env->exists() && ($env->get('APP_KEY') ?? '') === '' && getenv('APP_KEY') === false && $env->writable()) {
            $key = 'base64:'.base64_encode(random_bytes(32));

            try {
                $env->set(['APP_KEY' => $key]);
            } catch (Throwable) {
                // Kreator pokaże, że .env nie jest zapisywalny.
            }
        }

        if (($env->get('APP_KEY') ?? '') === '' && getenv('APP_KEY') === false) {
            // Plik .env nie do zapisania: klucz tymczasowy, tylko żeby wyświetlić ekran wymagań.
            $_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
            putenv('APP_KEY='.$_ENV['APP_KEY']);
        }
    }
}
