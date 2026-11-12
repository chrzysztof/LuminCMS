# Lumin CMS

Nowoczesna, open source alternatywa dla WordPressa: Laravel 13 + React (panel admina), publiczna strona renderowana serwerowo.
Od pierwszej chwili zawiera to, co w WordPressie wymaga ACF, Yoast SEO i Elementora:

- typy treści i taksonomie (własne również),
- pola własne w stylu ACF (36 typów),
- edytor wizualny GrapesJS generujący czysty, semantyczny HTML,
- SEO (tytuły, meta, canonical, sitemap.xml), media z WebP i własnymi rozmiarami,
- shortcode'y zgodne z WordPressem, import JSON, ustawienia (GA, GTM, Pixel).

Status: wczesny etap rozwoju (fundamenty + system pól).

## Uruchomienie (dev)

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed --seeder=CmsSeeder
npm install && npm run dev
php artisan serve
```

Licencja: GPL-3.0 (zobacz `LICENSE`).
