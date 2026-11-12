<?php

namespace App\Cms\Install;

use App\Cms\Roles\RoleRegistry;
use App\Cms\Settings\Settings;
use App\Models\User;
use Database\Seeders\CmsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Właściwa instalacja: migracje, dane startowe, konto administratora, ustawienia witryny i .env.
 * Połączenie z bazą musi być już skonfigurowane (DatabaseConfigurator::apply).
 */
class Installer
{
    public function __construct(private readonly EnvFile $env, private readonly Settings $settings, private readonly StarterContent $starter) {}

    /**
     * @param  array{site_name: string, url: string, locale: string, admin_name: string, admin_email: string, admin_password: string, starter?: bool}  $d
     * @param  bool  $production  ustawia APP_ENV=production i APP_DEBUG=false
     */
    public function install(array $d, bool $production = true): User
    {
        if (InstallState::installed()) {
            throw new RuntimeException('The CMS is already installed.');
        }

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => CmsSeeder::class, '--force' => true]);

        $admin = new User;
        $admin->forceFill([
            'name' => $d['admin_name'], 'email' => $d['admin_email'], 'password' => Hash::make($d['admin_password']),
            'role' => RoleRegistry::ADMINISTRATOR, 'locale' => $d['locale'], 'email_verified_at' => now(),
        ])->save();

        $this->settings->save('seo', ['site_name' => $d['site_name']]);

        if ($d['starter'] ?? false) {
            $this->starter->create($admin, $d['site_name']);
        }

        // Po migracjach tabele sesji, cache i kolejki istnieją, więc przechodzimy na sterownik bazy danych.
        $this->env->set([
            'APP_NAME' => $d['site_name'], 'APP_URL' => rtrim($d['url'], '/'), 'APP_LOCALE' => $d['locale'],
            'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database',
            ...($production ? ['APP_ENV' => 'production', 'APP_DEBUG' => false, 'LOG_LEVEL' => 'warning'] : []),
        ]);

        InstallState::markInstalled();

        return $admin;
    }
}
