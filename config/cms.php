<?php

return [
    // Instalacja motywów z ZIP-a w panelu. Motyw to zaufany kod (szablony Blade), więc na środowiskach,
    // gdzie administratorzy nie powinni wgrywać kodu, ustaw CMS_THEME_UPLOADS=false.
    'theme_uploads' => (bool) env('CMS_THEME_UPLOADS', true),

    // null = wykryj automatycznie (plik blokady lub konta w bazie); true/false wymusza stan, np. w testach.
    'installed' => env('CMS_INSTALLED'),

    // Plik blokady kreatora instalacji.
    'install_lock' => env('CMS_INSTALL_LOCK', storage_path('app/installed')),

    // Wersja systemu: plik VERSION w katalogu głównym (paczka wydania zapisuje tam numer z tagu).
    'version' => is_file(base_path('VERSION')) ? trim((string) file_get_contents(base_path('VERSION'))) : 'dev',

    // Sprawdzanie nowych wersji w GitHub Releases (jedno zapytanie na kilka godzin). CMS_UPDATE_CHECKS=false wyłącza
    // je całkowicie, wtedy system nie łączy się z żadnym zewnętrznym serwerem w tym celu.
    'update_checks' => (bool) env('CMS_UPDATE_CHECKS', true),

    // Repozytorium z wydaniami (właściciel/nazwa).
    'update_repo' => env('CMS_UPDATE_REPO', 'chrzysztof/LuminCMS'),

    // Aktualizacja jednym kliknięciem z panelu. CMS_SELF_UPDATE=false zostawia samo powiadamianie, a aktualizację
    // trzeba wykonać ręcznie (np. wdrożeniem z gita lub przez CI).
    'self_update' => (bool) env('CMS_SELF_UPDATE', true),
];
