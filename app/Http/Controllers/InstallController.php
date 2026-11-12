<?php

namespace App\Http\Controllers;

use App\Cms\I18n\Locales;
use App\Cms\Install\DatabaseConfigurator;
use App\Cms\Install\EnvFile;
use App\Cms\Install\InstallData;
use App\Cms\Install\Installer;
use App\Cms\Install\Requirements;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

/** Kreator instalacji w przeglądarce: wymagania → baza danych → witryna i administrator. */
class InstallController extends Controller
{
    public function __construct(private readonly Requirements $requirements, private readonly DatabaseConfigurator $database) {}

    public function language(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(array_keys(Locales::SUPPORTED))]]);

        return back()->withCookie(Cookie::make(Locales::COOKIE, $data['locale'], 60 * 24 * 365));
    }

    public function requirements(): View
    {
        return view('install.requirements', ['step' => 1, 'checks' => $this->requirements->checks(), 'passes' => $this->requirements->passes()]);
    }

    public function database(): View|RedirectResponse
    {
        if (! $this->requirements->passes()) {
            return redirect('/install');
        }

        $env = app(EnvFile::class);
        $driver = $env->get('DB_CONNECTION');

        return view('install.database', [
            'step' => 2, 'drivers' => $this->requirements->drivers(),
            'values' => [
                'driver' => array_key_exists((string) $driver, $this->requirements->drivers()) ? $driver : array_key_first($this->requirements->drivers()),
                'host' => $env->get('DB_HOST') ?: '127.0.0.1', 'port' => $env->get('DB_PORT') ?: '3306',
                'database' => $env->get('DB_DATABASE') ?: '', 'username' => $env->get('DB_USERNAME') ?: '',
            ],
        ]);
    }

    public function saveDatabase(Request $request): RedirectResponse
    {
        abort_unless($this->requirements->passes(), 403);

        $data = $request->validate(InstallData::databaseRules($this->requirements->drivers()), [], $this->attributes());

        if (($error = $this->database->test($data)) !== null) {
            return back()->withInput($request->except('password'))->withErrors(['connection' => __('Could not connect to the database: :error', ['error' => $error])]);
        }

        try {
            $this->database->apply($data, app(EnvFile::class));
        } catch (Throwable) {
            return back()->withInput($request->except('password'))->withErrors(['connection' => __('Could not write the .env file. Check its permissions.')]);
        }

        return redirect('/install/site');
    }

    public function site(Request $request): View|RedirectResponse
    {
        if (! $this->requirements->passes() || $this->database->test(['driver' => config('database.default')] + $this->currentConnection()) !== null) {
            return redirect('/install/database');
        }

        return view('install.site', ['step' => 3, 'url' => $request->getSchemeAndHttpHost()]);
    }

    public function install(Request $request, Installer $installer): View|RedirectResponse
    {
        abort_unless($this->requirements->passes(), 403);

        $rules = InstallData::siteRules();
        $rules['admin_password'][] = 'confirmed';
        $data = $request->validate($rules, [], $this->attributes());
        $data['starter'] = $request->boolean('starter');

        try {
            $admin = $installer->install($data);
        } catch (Throwable $e) {
            Log::error('Installation failed: '.$e->getMessage());
            report($e);

            return back()->withInput($request->except(['admin_password', 'admin_password_confirmation']))
                ->withErrors(['install' => __('Installation failed: :error', ['error' => $e->getMessage()])]);
        }

        return view('install.done', ['step' => 4, 'email' => $admin->email, 'login' => rtrim($data['url'], '/').'/login']);
    }

    /** @return array<string, mixed> */
    private function currentConnection(): array
    {
        $c = config('database.connections.'.config('database.default'), []);

        return ['host' => $c['host'] ?? null, 'port' => $c['port'] ?? null, 'database' => $c['database'] ?? null, 'username' => $c['username'] ?? null, 'password' => $c['password'] ?? null];
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        return [
            'driver' => __('Database type'), 'host' => __('Host'), 'port' => __('Port'), 'database' => __('Database name'),
            'username' => __('Username'), 'password' => __('Password'), 'site_name' => __('Site name'), 'url' => __('Site address'),
            'locale' => __('Panel language'), 'admin_name' => __('Name'), 'admin_email' => __('Email'), 'admin_password' => __('Password'),
        ];
    }
}
