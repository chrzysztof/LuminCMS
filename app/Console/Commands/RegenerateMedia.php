<?php

namespace App\Console\Commands;

use App\Cms\Media\Media;
use App\Cms\Media\MediaManager;
use Illuminate\Console\Command;

class RegenerateMedia extends Command
{
    protected $signature = 'media:regenerate';

    protected $description = 'Odtwarza warianty WebP wszystkich obrazów według aktualnych rozmiarów';

    public function handle(MediaManager $manager): int
    {
        $count = 0;

        Media::where('mime', 'like', 'image/%')->each(function (Media $media) use ($manager, &$count) {
            $manager->regenerate($media);
            $count++;
        });

        $this->info("Odtworzono warianty dla {$count} obrazów.");

        return self::SUCCESS;
    }
}
