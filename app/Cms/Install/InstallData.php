<?php

namespace App\Cms\Install;

use App\Cms\I18n\Locales;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Reguły walidacji danych instalacji, wspólne dla kreatora i komendy CLI. */
class InstallData
{
    /** @return array<string, array> */
    public static function databaseRules(array $available): array
    {
        return [
            'driver' => ['required', Rule::in(array_keys($available))],
            'host' => ['required_unless:driver,sqlite', 'nullable', 'string', 'max:255'],
            'port' => ['required_unless:driver,sqlite', 'nullable', 'integer', 'between:1,65535'],
            'database' => ['required_unless:driver,sqlite', 'nullable', 'string', 'max:255'],
            'username' => ['required_unless:driver,sqlite', 'nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, array> */
    public static function siteRules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:255'],
            'locale' => ['required', Rule::in(array_keys(Locales::SUPPORTED))],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email:rfc', 'max:255'],
            'admin_password' => ['required', 'string', Password::min(8)],
            'starter' => ['boolean'],
        ];
    }
}
