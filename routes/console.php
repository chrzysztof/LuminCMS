<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Planowanie publikacji: wymaga jednego wpisu crona `* * * * * php artisan schedule:run`.
Schedule::command('cms:publish-scheduled')->everyMinute()->withoutOverlapping();

// Sprawdzanie nowych wersji (wynik trafia do cache i pokazuje się jako powiadomienie w panelu).
Schedule::command('cms:check-updates')->twiceDaily(6, 18);
