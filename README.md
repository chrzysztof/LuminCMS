# Lumin CMS

**A modern, open-source content management system for people who build websites for a living.**

Lumin is built on Laravel 13 with a React admin panel and server-rendered public pages. It aims to give you everything a typical production site needs out of the box — structured content, flexible custom fields, a visual page editor, a media library and SEO tooling — without assembling a stack of third-party plugins first.

## Highlights

- **Content types and taxonomies** — pages and posts out of the box, plus your own types and taxonomies.
- **Custom fields** — a field-group builder with 36 planned field types (35 available today, including Link and Page Link, including Repeater and OpenStreetMap maps), conditional logic, location rules and JSON import/export.
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

The admin panel ships in eleven languages: Polski, English, Deutsch, Español, Українська, Čeština, Português (Brazilian), Italiano, Français, Magyar and Nederlands. Users pick a language from the user menu or in Settings → Appearance (stored per user, with a cookie fallback for guests; otherwise the browser language is detected).

Translations live in `lang/{locale}.json` (UI strings, keyed by source text) and `lang/{locale}/*.php` (framework messages). In React use `t('Text', { name })`; in PHP use `__('Text')`. Adding a language means registering it in `App\Cms\I18n\Locales` and adding its JSON file. The public site and labels stored in the database (content types, field groups) are not translated yet.

## Taxonomies and shortcodes

**Taxonomies** (Content → Categories and tags): hierarchical (categories) or flat (tags) term lists, manageable in the admin, with custom taxonomies attachable to any content type. Terms are assigned in the content editor sidebar, and also drive field-group location rules.

**Shortcodes** work in page output and in rich-text field values: `[name attr="value"]`, `[name]content[/name]`, and `[[name]]` to print a literal `[name]`. Content of `<code>`, `<pre>`, `<script>` and `<style>` is left untouched. Built in: `[year]`, `[date format="d.m.Y"]`, `[site_name]`, `[site_url]`, `[button url="/contact" style="secondary" new="1"]Label[/button]`, `[field name="phone" id="12"]`, `[link id="12"]Text[/link]`, `[posts type="post" limit="5" taxonomy="category" term="news" orderby="date" order="desc"]` and `[terms taxonomy="category"]`. Register your own:

```php
app(\App\Cms\Shortcodes\Shortcodes::class)->add('hello', fn (array $attrs, string $content, ?Content $current) => '<b>'.e($attrs['name'] ?? 'world').'</b>');
```

Handlers return trusted HTML, so escape attribute values yourself.

## Themes

A theme is a folder in `themes/{slug}/` with a `theme.json` manifest and Blade views (exposed as the `theme::` namespace). Missing views fall back to the default theme (`lumin-default`), so a theme only needs to override what it changes.

- **Page templates** are declared in `theme.json` and picked per entry in the editor.
- **Template hierarchy:** chosen template, `single-{type}`, `single`; archives use `archive-{type}` / `archive`; taxonomy terms use `taxonomy-{key}` / `taxonomy` / `archive`.
- **Public archives:** `/blog` (content type URL prefix) and `/kategoria/{term}` (taxonomy URL prefix, editable in the taxonomy panel), both with pagination, SEO and sitemap entries.
- **Assets** live in `themes/{slug}/assets` and are served through `theme-assets/{slug}/...`.
- **Global parts:** the theme declares slots (`parts` in `theme.json`, e.g. header and footer). Appearance → Parts assigns a part, edited in the visual editor, to each slot; an empty slot uses the theme's default markup. Parts are admin-only and never public.
- **Menus:** Appearance → Menu builds multi-level menus (content, terms, custom links, headings) and assigns them to theme locations (`menus` in `theme.json`). A top-level item can be a **mega menu**: its children become columns, with an optional extra panel from a global part. Insert a menu with the editor's Menu block or `[menu name="slug"]` / `[menu location="primary"]`. Output is accessible (`aria-expanded`, keyboard, mobile accordion) and styled by the theme's `menu.css` / `menu.js`.
- **Customizer:** Appearance → Customize edits the theme's design tokens (declared in `theme.json` as `tokens`: `color`, `select`, `size`) with a live preview in a device-sized frame. Values are validated, stored per theme and emitted as CSS variables in `:root` (also in the editor canvas via `/theme-vars.css`). Fonts are system stacks only, no external CDN.
- **Theme blocks:** a theme adds editor blocks in `blocks/{name}/` with `block.json` (label, category, icon, `fields` using the same field types as custom fields), `render.blade.php` (receives `$attrs`) and optional `style.css`. In the visual editor they appear under their category, open a settings dialog built from the fields, and preview live. The page stores only the block name and validated values (as a `[block]` shortcode), so template changes apply immediately and dynamic blocks stay current.
- **Child themes:** add `"parent": "slug"` to `theme.json`. Views, assets, blocks and the manifest lists (`templates`, `tokens`, `parts`, `menus`) are inherited and merged by key; the child overrides only what it redeclares. Lookup order is child → parents → default theme. Missing parents and cycles are detected and block activation.
- **Theme management:** Appearance → Themes lists installed themes (screenshot, version, author, parent), activates, exports a theme as ZIP and deletes it (not the default, the active theme or a parent of another theme). Install from a ZIP validates the archive before extracting: safe paths only (no `..`, absolute paths or symlinks), an extension whitelist (PHP allowed only as `*.blade.php`), no hidden files, file-count and size limits, a `theme.json` with a `name`, and either `views/layout.blade.php` or an installed parent. A theme is trusted code (Blade runs on the server), so set `CMS_THEME_UPLOADS=false` to disable uploads on hosts where only deploys should add themes.
- `php artisan cms:theme` lists themes, `php artisan cms:theme {slug}` activates one.

## Container fields

Besides Repeater, custom fields include **Group**, **Flexible Content** and **Clone**, all free and in the core.

- **Group** stores its sub-fields as one object (`address.street`, `address.city`); nested required fields are validated even when the group is untouched.
- **Flexible Content** is a list of rows, each with one of the declared layouts and that layout's own fields. Layouts have optional min/max counts; rows are stored as `{"_layout": "text", ...}` and returned in templates with formatted values.
- **Clone** reuses other field groups (a whole group or single fields) without copying definitions. `seamless` inserts the fields as if they were your own (optionally prefixing names and labels, conditions are rewritten); `group` nests them under the clone's name. Clones expand on the server before validation, forms and template reads, work inside other containers, and cycles are cut off.
- Containers nest (up to the builder's depth limit) and relation fields inside them work, including inside Flexible Content layouts.

## Import and export

Structure → Import and export moves content types, taxonomies with terms, field groups and (optionally) content between installations as one JSON file (schema `"lumin": 1`, objects `content_types`, `taxonomies`, `field_groups`, `contents`). Content references parents, terms and types by key or slug, never by database id.

- **Dry run:** the whole import runs in one database transaction. Any error, or the dry-run switch, rolls everything back, so the report shows exactly what a real import would do and a bad file never leaves half-imported data.
- **Existing items** (same key or slug) are skipped by default, or overwritten with "update". System types and taxonomies are never overwritten.
- Field groups, content fields and slugs go through the same validation as the panel forms; visual-editor content (`builder_data`) is re-rendered to clean HTML on import.
- Media library files are not transferred, so fields holding media ids need to be filled in afterwards.

## Installation

### Release package (no Composer or Node needed)

Download `lumin-cms-<version>.zip` from the GitHub releases page, unpack it on your server and point the web root at the `public/` directory. Open the site in a browser: the installer starts by itself.

The installer (`/install`) checks the requirements (PHP version, extensions, writable folders), tests and saves the database connection (SQLite, MySQL/MariaDB or PostgreSQL), then creates the administrator account and site settings, with optional sample content (home page, about page, a sample post and a main menu). The wizard is available in all eleven admin languages. It creates `.env` and the application key on the first request, switches to production settings (`APP_ENV=production`, `APP_DEBUG=false`) and to database-backed sessions, cache and queue when it finishes.

Security notes:

- The installer disables itself once it finishes: `/install` returns 404 and a lock file `storage/app/installed` is written. Install on a private URL or finish the wizard right after unpacking, because until then anyone who reaches the site can run it.
- Point the web server at `public/`, not at the project root, so `.env` is never served.
- To run the installer again, remove `storage/app/installed` and use an empty database.

### Command line

```bash
php artisan cms:install
```

It asks for the same data as the wizard. Everything can be passed as options for unattended installs:

```bash
php artisan cms:install --no-interaction --db=mysql --db-host=127.0.0.1 --db-name=lumin --db-user=lumin --db-password=secret \
  --name="My site" --url=https://example.com --locale=en \
  --admin-name=Ada --admin-email=ada@example.com --admin-password='correct horse battery' --starter
```

Use `--dev` to keep `APP_ENV` and `APP_DEBUG` from your `.env` instead of switching to production.

### Building a release

`scripts/build-release.sh [version]` produces `dist/lumin-cms-<version>.zip` (with `vendor/` and the built front end). Pushing a `v*` tag runs the same script in GitHub Actions and attaches the ZIP to a GitHub release.

## Requirements

- PHP 8.3+ (extensions: ctype, dom, fileinfo, json, mbstring, openssl, pdo, tokenizer, xml; GD or Imagick for media; zip for theme uploads)
- SQLite, MySQL/MariaDB or PostgreSQL
- Composer and Node.js 20+ only when installing from source

## Getting started (from source)

```bash
composer install
npm install && npm run build   # or npm run dev while developing
php artisan cms:install --dev
php artisan serve
```

Or open the site and use the browser installer. Then open the admin panel at `/cms`.

## Testing

```bash
php artisan test
npm run check
```

## Contributing

Issues and pull requests are welcome. Please run the test suite and the linters before submitting changes.

## License

Released under the GPL-3.0 license. See [`LICENSE`](LICENSE).
