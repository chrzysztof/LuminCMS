<?php

namespace App\Console\Commands;

use App\Cms\Updates\UpdateChecker;
use App\Cms\Updates\UpdateException;
use App\Cms\Updates\Updater;
use Illuminate\Console\Command;

class UpdateCms extends Command
{
    protected $signature = 'cms:update {--check : Tylko sprawdź i wypisz dostępną wersję}';

    protected $description = 'Pobiera i instaluje najnowszą wersję Lumin CMS z GitHub Releases';

    public function handle(UpdateChecker $checker, Updater $updater): int
    {
        $release = $checker->latest(force: true);

        if (! $release || ! $checker->updateAvailable()) {
            $this->info("Masz aktualną wersję ({$checker->current()}).");

            return self::SUCCESS;
        }

        $this->line("Dostępna wersja {$release->version} (masz {$checker->current()}).");

        if ($this->option('check')) {
            return self::SUCCESS;
        }

        try {
            $updater->logUsing(fn (string $m) => $this->line("  {$m}"))->install($release);
        } catch (UpdateException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
