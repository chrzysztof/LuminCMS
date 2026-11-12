<?php

return [
    // Instalacja motywów z ZIP-a w panelu. Motyw to zaufany kod (szablony Blade), więc na środowiskach,
    // gdzie administratorzy nie powinni wgrywać kodu, ustaw CMS_THEME_UPLOADS=false.
    'theme_uploads' => (bool) env('CMS_THEME_UPLOADS', true),

    // null = wykryj automatycznie (plik blokady lub konta w bazie); true/false wymusza stan, np. w testach.
    'installed' => env('CMS_INSTALLED'),

    // Plik blokady kreatora instalacji.
    'install_lock' => env('CMS_INSTALL_LOCK', storage_path('app/installed')),
];
