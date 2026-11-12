<?php

namespace App\Jobs;

use App\Cms\Media\Media;
use App\Cms\Media\MediaManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Odtwarza warianty obrazów (po zmianie definicji rozmiarów). */
class RegenerateMediaVariants implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $mediaId) {}

    public function handle(MediaManager $manager): void
    {
        if ($media = Media::find($this->mediaId)) {
            $manager->regenerate($media);
        }
    }
}
