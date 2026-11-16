<?php

namespace App\Console\Commands;

use App\Cms\Updates\UpdateChecker;
use Illuminate\Console\Command;

class CheckUpdates extends Command
{
    protected $signature = 'cms:check-updates';

    protected $description = 'Sprawdza, czy jest nowa wersja Lumin CMS';

    public function handle(UpdateChecker $checker): int
    {
        if (! $checker->enabled()) {
            $this->warn('Sprawdzanie aktualizacji jest wyłączone (CMS_UPDATE_CHECKS=false).');

            return self::SUCCESS;
        }

        $release = $checker->refresh();
        $error = $checker->state()['error'] ?? null;

        if ($error) {
            $this->error("Nie udało się sprawdzić: {$error}");

            return self::FAILURE;
        }

        $this->line($release && $checker->updateAvailable()
            ? "Dostępna nowa wersja: {$release->version} (masz {$checker->current()})."
            : "Masz aktualną wersję ({$checker->current()}).");

        return self::SUCCESS;
    }
}
