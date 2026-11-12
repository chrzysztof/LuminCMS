<?php

namespace App\Cms\Install;

/** Sprawdza, czy serwer spełnia wymagania CMS. Każda kontrola: klucz, etykieta, wynik i czy jest wymagana. */
class Requirements
{
    private const EXTENSIONS = ['ctype', 'dom', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml'];

    /** @return list<array{key: string, label: string, ok: bool, required: bool, hint: string}> */
    public function checks(): array
    {
        $checks = [[
            'key' => 'php', 'label' => __('PHP 8.3 or newer (found :version)', ['version' => PHP_VERSION]), 'ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'required' => true, 'hint' => __('Upgrade PHP to 8.3 or newer.'),
        ]];

        foreach (self::EXTENSIONS as $ext) {
            $checks[] = ['key' => 'ext-'.$ext, 'label' => __('PHP extension: :name', ['name' => $ext]), 'ok' => extension_loaded($ext), 'required' => true, 'hint' => __('Enable the :name PHP extension.', ['name' => $ext])];
        }

        $checks[] = [
            'key' => 'database', 'label' => __('Database driver: SQLite, MySQL/MariaDB or PostgreSQL'),
            'ok' => $this->drivers() !== [], 'required' => true, 'hint' => __('Enable pdo_sqlite, pdo_mysql or pdo_pgsql.'),
        ];
        $checks[] = [
            'key' => 'images', 'label' => __('Image processing (GD or Imagick)'),
            'ok' => extension_loaded('gd') || extension_loaded('imagick'), 'required' => false, 'hint' => __('Without it, media thumbnails are not generated.'),
        ];
        $checks[] = ['key' => 'zip', 'label' => __('PHP extension: :name', ['name' => 'zip']), 'ok' => extension_loaded('zip'), 'required' => false, 'hint' => __('Needed to install themes from ZIP files.')];

        foreach ([
            'env' => ['.env', base_path('.env'), true],
            'storage' => ['storage/', storage_path(), true],
            'cache' => ['bootstrap/cache/', base_path('bootstrap/cache'), true],
            'themes' => ['themes/', base_path('themes'), false],
        ] as $key => [$label, $path, $required]) {
            $checks[] = [
                'key' => 'write-'.$key, 'label' => __('Writable: :path', ['path' => $label]), 'ok' => is_writable($path) || (! file_exists($path) && is_writable(dirname($path))),
                'required' => $required, 'hint' => __('Give the web server write access to :path.', ['path' => $label]),
            ];
        }

        return $checks;
    }

    public function passes(): bool
    {
        foreach ($this->checks() as $check) {
            if ($check['required'] && ! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, string> dostępne sterowniki: klucz => nazwa */
    public function drivers(): array
    {
        return array_filter([
            'sqlite' => extension_loaded('pdo_sqlite') ? 'SQLite' : null,
            'mysql' => extension_loaded('pdo_mysql') ? 'MySQL / MariaDB' : null,
            'pgsql' => extension_loaded('pdo_pgsql') ? 'PostgreSQL' : null,
        ]);
    }
}
