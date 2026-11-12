<?php

namespace App\Cms\Settings;

use Illuminate\Support\Facades\DB;

/**
 * Ustawienia witryny pogrupowane tematycznie (seo, social, analytics...).
 * Wartości domyślne definiuje kod, w bazie trzymamy tylko to, co zmienił użytkownik.
 */
class Settings
{
    /** @var array<string, array> */
    private array $memo = [];

    /** @param  array<string, array<string, mixed>>  $defaults */
    public function __construct(private array $defaults = []) {}

    public function define(string $group, array $defaults): void
    {
        $this->defaults[$group] = $defaults;
        unset($this->memo[$group]);
    }

    /** @return array<string, mixed> */
    public function group(string $group): array
    {
        return $this->memo[$group] ??= array_replace(
            $this->defaults[$group] ?? [],
            (array) (json_decode((string) DB::table('settings')->where('group', $group)->value('value'), true) ?? []),
        );
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->group($group)[$key] ?? $default;
    }

    /** Zapisuje tylko klucze znane z wartości domyślnych grupy. */
    public function save(string $group, array $values): void
    {
        $allowed = array_intersect_key($values, $this->defaults[$group] ?? $values);

        DB::table('settings')->updateOrInsert(
            ['group' => $group],
            ['value' => json_encode(array_replace($this->group($group), $allowed), JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()],
        );

        unset($this->memo[$group]);
    }
}
