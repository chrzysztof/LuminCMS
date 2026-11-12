<?php

namespace App\Console\Commands;

use App\Cms\Themes\ThemeManager;
use Illuminate\Console\Command;

class ThemeCommand extends Command
{
    protected $signature = 'cms:theme {slug? : Motyw do aktywacji; bez argumentu wypisuje dostępne}';

    protected $description = 'Wypisuje motywy z katalogu themes/ albo aktywuje wskazany';

    public function handle(ThemeManager $themes): int
    {
        if ($slug = $this->argument('slug')) {
            if (! $themes->has($slug)) {
                $this->error("Nie ma motywu „{$slug}”.");

                return self::FAILURE;
            }

            $themes->activate($slug);
            $this->info("Aktywny motyw: {$slug}");

            return self::SUCCESS;
        }

        $active = $themes->active()->slug;

        foreach ($themes->all() as $theme) {
            $this->line(($theme->slug === $active ? '* ' : '  ').$theme->slug.'  '.$theme->name().' '.($theme->manifest['version'] ?? ''));
        }

        return self::SUCCESS;
    }
}
