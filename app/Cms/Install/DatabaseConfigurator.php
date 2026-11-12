<?php

namespace App\Cms\Install;

use Illuminate\Support\Facades\DB;
use Throwable;

/** Testuje połączenie z bazą, zapisuje je do .env i przełącza bieżący proces na nowe połączenie. */
class DatabaseConfigurator
{
    public const DEFAULT_PORTS = ['mysql' => 3306, 'pgsql' => 5432];

    /** @return string|null komunikat błędu albo null, gdy połączenie działa */
    public function test(array $c): ?string
    {
        try {
            $this->prepareSqlite($c);
            config(['database.connections.install_test' => $this->connection($c)]);
            DB::purge('install_test');
            DB::connection('install_test')->getPdo();
            DB::purge('install_test');

            return null;
        } catch (Throwable $e) {
            return trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '');
        }
    }

    /** Zapisuje dane do .env i przełącza domyślne połączenie w tym procesie. */
    public function apply(array $c, EnvFile $env): void
    {
        $this->prepareSqlite($c);

        if ($c['driver'] === 'sqlite') {
            $env->set(['DB_CONNECTION' => 'sqlite', 'DB_HOST' => null, 'DB_PORT' => null, 'DB_DATABASE' => null, 'DB_USERNAME' => null, 'DB_PASSWORD' => null]);
        } else {
            $env->set([
                'DB_CONNECTION' => $c['driver'], 'DB_HOST' => $c['host'], 'DB_PORT' => (int) $c['port'],
                'DB_DATABASE' => $c['database'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'] ?? '',
            ]);
        }

        config(['database.default' => $c['driver'], 'database.connections.'.$c['driver'] => $this->connection($c)]);
        DB::purge($c['driver']);
    }

    /** @return array<string, mixed> */
    private function connection(array $c): array
    {
        $base = config('database.connections.'.$c['driver'], []);

        if ($c['driver'] === 'sqlite') {
            return array_replace($base, ['database' => database_path('database.sqlite'), 'url' => null, 'foreign_key_constraints' => true]);
        }

        return array_replace($base, [
            'url' => null, 'host' => $c['host'], 'port' => (int) $c['port'],
            'database' => $c['database'], 'username' => $c['username'], 'password' => $c['password'] ?? '',
        ]);
    }

    private function prepareSqlite(array $c): void
    {
        if ($c['driver'] === 'sqlite' && ! is_file(database_path('database.sqlite'))) {
            @touch(database_path('database.sqlite'));
        }
    }
}
