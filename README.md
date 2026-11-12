# Lumin CMS

**A modern, open-source content management system for people who build websites for a living.**

Lumin is built on Laravel 13 with a React admin panel and server-rendered public pages. It aims to give you everything a typical production site needs out of the box — structured content, flexible custom fields, a visual page editor, a media library and SEO tooling — without assembling a stack of third-party plugins first.

## Highlights

- **Content types and taxonomies** — pages and posts out of the box, plus your own types and taxonomies.
- **Custom fields** — a field-group builder with 36 planned field types (22 available today), conditional logic, location rules and JSON import/export.
- **Visual editor** — a drag-and-drop page builder (GrapesJS) that outputs clean, semantic HTML with no inline styles or generated IDs.
- **Media library** — automatic WebP conversion, configurable image sizes, focal points, per-size crops and responsive `<picture>` output.
- **SEO built in** — titles, meta descriptions, canonical URLs, sitemap and image alt text *(in progress)*.
- **Shortcodes, JSON importer and site settings** — analytics, tag manager and pixel integrations *(planned)*.
- **Fast by default** — server-rendered Blade templates on the public site, cached builder output, a lightweight front end.

## Status

Early development. The foundation, admin panel, media library, visual editor and field-group builder are working; the remaining field types, SEO module and settings are on the roadmap.

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+
- SQLite, MySQL or PostgreSQL

## Getting started

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan db:seed --class=CmsSeeder
php artisan storage:link
npm install && npm run dev
php artisan serve
```

Then open the admin panel at `/cms` after registering a user.

## Testing

```bash
php artisan test
npm run check
```

## Contributing

Issues and pull requests are welcome. Please run the test suite and the linters before submitting changes.

## License

Released under the GPL-3.0 license. See [`LICENSE`](LICENSE).
