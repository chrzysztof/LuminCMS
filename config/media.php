<?php

return [
    // Dysk Laravela, na którym leżą pliki (config/filesystems.php).
    'disk' => env('MEDIA_DISK', 'public'),

    // Jakość WebP (1-100). Źródła z listy `lossless_sources` są kodowane bezstratnie.
    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 82),
    'lossless_sources' => ['image/png'],

    // Najdłuższy bok wariantu "full" (oryginał zostaje bez zmian).
    'max_dimension' => 2560,

    // Maksymalny rozmiar uploadu w KB.
    'max_upload_kb' => 20480,

    // Dozwolone typy MIME (rozpoznawane po zawartości pliku). SVG celowo pominięty (ryzyko XSS).
    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'text/plain', 'text/csv', 'application/zip',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'audio/mpeg', 'video/mp4',
    ],

    // Typy, z których generujemy warianty WebP (GIF zostaje oryginałem, by nie tracić animacji).
    'processable_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
];
