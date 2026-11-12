<?php

return [
    // Instalacja motywów z ZIP-a w panelu. Motyw to zaufany kod (szablony Blade), więc na środowiskach,
    // gdzie administratorzy nie powinni wgrywać kodu, ustaw CMS_THEME_UPLOADS=false.
    'theme_uploads' => (bool) env('CMS_THEME_UPLOADS', true),
];
