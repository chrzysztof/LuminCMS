<?php

namespace App\Console\Commands;

use App\Cms\Content\Content;
use Illuminate\Console\Command;

/** Publikuje treści zaplanowane, którym minął termin (uruchamiane co minutę przez scheduler). */
class PublishScheduled extends Command
{
    protected $signature = 'cms:publish-scheduled';

    protected $description = 'Publikuje zaplanowane treści, którym minął termin';

    public function handle(): int
    {
        $count = Content::where('status', Content::STATUS_SCHEDULED)
            ->where('published_at', '<=', now())
            ->update(['status' => Content::STATUS_PUBLISHED]);

        $this->info("Opublikowano: {$count}");

        return self::SUCCESS;
    }
}
