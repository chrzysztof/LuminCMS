# Lumin CMS

**A modern, open-source content management system for people who build websites for a living.**

Lumin is built on Laravel 13 with a React admin panel and server-rendered public pages. It aims to give you everything a typical production site needs out of the box — structured content, flexible custom fields, a visual page editor, a media library and SEO tooling — without assembling a stack of third-party plugins first.

## Highlights

- **Content types and taxonomies** — pages and posts out of the box, plus your own types and taxonomies.
- **Custom fields** — a field-group builder with 36 planned field types (30 available today, including Repeater and OpenStreetMap maps), conditional logic, location rules and JSON import/export.
- **Visual editor** — a drag-and-drop page builder (GrapesJS) that outputs clean, semantic HTML with no inline styles or generated IDs.
- **Media library** — automatic WebP conversion, configurable image sizes, focal points, per-size crops and responsive `<picture>` output.
- **SEO built in** — title templates, meta descriptions, canonical URLs, robots directives, Open Graph, Twitter cards, JSON-LD, `sitemap.xml`, `robots.txt` and a snippet preview with focus-keyword checks.
- **Redirects and 404 handling** — 301/302/307/308/410 redirects, automatic redirects when a page URL changes, a 404 monitor and a customizable 404 page.
- **Site settings** — Google Analytics 4, Tag Manager and Meta Pixel with a cookie-consent banner, social profiles, custom head and footer code.
- **Users and roles** — the familiar five roles (Administrator, Editor, Author, Contributor, Subscriber) plus two roles for external SEO agencies (SEO Manager, SEO Specialist) with the same capability model: contributors submit posts for review, authors publish their own, editors manage all content, administrators manage settings and users. Includes a user manager, last-administrator protection, content reassignment on delete and configurable public registration.
- **Scheduled publishing** — pick a future date and the content goes live automatically (a per-minute scheduler command; one cron entry: `* * * * * php artisan schedule:run`).
- **Shortcodes and JSON importer** _(planned)_.
- **Fast by default** — server-rendered Blade templates on the public site, cached builder output, a lightweight front end.

## Status

Early development. The foundation, admin panel, media library, visual editor and field-group builder are working; the SEO module, redirects, site settings and user roles are working; the remaining field types are on the roadmap.

## Admin languages

The admin panel ships in seven languages: Polski, English, Deutsch, Español, Українська, Čeština and Português (Brazilian). Users pick a language from the user menu or in Settings → Appearance (stored per user, with a cookie fallback for guests; otherwise the browser language is detected).

Translations live in `lang/{locale}.json` (UI strings, keyed by source text) and `lang/{locale}/*.php` (framework messages). In React use `t('Text', { name })`; in PHP use `__('Text')`. Adding a language means registering it in `App\Cms\I18n\Locales` and adding its JSON file. The public site and labels stored in the database (content types, field groups) are not translated yet.

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
