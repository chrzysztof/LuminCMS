<?php

namespace App\Console\Commands;

use App\Cms\I18n\Locales;
use App\Cms\Install\DatabaseConfigurator;
use App\Cms\Install\EnvFile;
use App\Cms\Install\InstallData;
use App\Cms\Install\Installer;
use App\Cms\Install\InstallState;
use App\Cms\Install\Requirements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'cms:install
        {--db= : Database driver: sqlite, mysql or pgsql}
        {--db-host=127.0.0.1} {--db-port=} {--db-name=} {--db-user=} {--db-password=}
        {--name= : Site name} {--url= : Site address, e.g. https://example.com} {--locale= : Panel language code}
        {--admin-name=} {--admin-email=} {--admin-password=}
        {--starter : Add sample content (home page, about page, sample post, main menu)}
        {--dev : Keep APP_ENV and APP_DEBUG from .env (do not switch to production)}';

    protected $description = 'Install Lumin CMS: database, administrator account and site settings';

    public function handle(Requirements $requirements, DatabaseConfigurator $database, Installer $installer): int
    {
        if (InstallState::installed()) {
            $this->components->error('Lumin CMS is already installed. To run the installer again, remove '.InstallState::lockPath().' and use an empty database.');

            return self::FAILURE;
        }

        $env = new EnvFile(base_path('.env'));

        if (! $env->exists() && is_file(base_path('.env.example'))) {
            copy(base_path('.env.example'), base_path('.env'));
        }

        if (($env->get('APP_KEY') ?? '') === '') {
            Artisan::call('key:generate', ['--force' => true]);
        }

        $failed = false;
        foreach ($requirements->checks() as $check) {
            if (! $check['ok']) {
                $this->components->{$check['required'] ? 'error' : 'warn'}($check['label'].' — '.$check['hint']);
                $failed = $failed || $check['required'];
            }
        }

        if ($failed) {
            return self::FAILURE;
        }

        $interactive = $this->input->isInteractive();
        $drivers = $requirements->drivers();

        $c = ['driver' => $this->option('db') ?: ($interactive ? select('Database', $drivers, array_key_first($drivers)) : array_key_first($drivers))];

        if (($c['driver'] ?? null) !== 'sqlite') {
            $c += [
                'host' => $this->option('db-host') ?: ($interactive ? text('Database host', default: '127.0.0.1') : '127.0.0.1'),
                'port' => $this->option('db-port') ?: ($interactive ? text('Database port', default: (string) (DatabaseConfigurator::DEFAULT_PORTS[$c['driver']] ?? 3306)) : (DatabaseConfigurator::DEFAULT_PORTS[$c['driver']] ?? 3306)),
                'database' => $this->option('db-name') ?: ($interactive ? text('Database name', required: true) : null),
                'username' => $this->option('db-user') ?: ($interactive ? text('Database user', required: true) : null),
                'password' => $this->option('db-password') ?? ($interactive ? password('Database password') : ''),
            ];
        }

        if ($errors = $this->validate($c, InstallData::databaseRules($drivers))) {
            return $this->failWith($errors);
        }

        if (($error = $database->test($c)) !== null) {
            $this->components->error('Could not connect to the database: '.$error);

            return self::FAILURE;
        }

        $database->apply($c, $env);

        $d = [
            'site_name' => $this->option('name') ?: ($interactive ? text('Site name', default: 'My site', required: true) : 'My site'),
            'url' => $this->option('url') ?: ($interactive ? text('Site address', default: (string) config('app.url'), required: true) : (string) config('app.url')),
            'locale' => $this->option('locale') ?: ($interactive ? select('Panel language', Locales::SUPPORTED, 'en') : 'en'),
            'admin_name' => $this->option('admin-name') ?: ($interactive ? text('Administrator name', required: true) : null),
            'admin_email' => $this->option('admin-email') ?: ($interactive ? text('Administrator email', required: true) : null),
            'admin_password' => $this->option('admin-password') ?: ($interactive ? password('Administrator password (min. 8 characters)', required: true) : null),
            'starter' => $this->option('starter') || ($interactive && confirm('Add sample content?', true)),
        ];

        if ($errors = $this->validate($d, InstallData::siteRules())) {
            return $this->failWith($errors);
        }

        try {
            $installer->install($d, production: ! $this->option('dev'));
        } catch (Throwable $e) {
            $this->components->error('Installation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Lumin CMS is installed. Sign in at '.rtrim($d['url'], '/').'/login as '.$d['admin_email'].'.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function validate(array $data, array $rules): array
    {
        $v = Validator::make($data, $rules);

        return $v->fails() ? $v->errors()->all() : [];
    }

    /** @param list<string> $errors */
    private function failWith(array $errors): int
    {
        foreach ($errors as $e) {
            $this->components->error($e);
        }

        return self::FAILURE;
    }
}
