# Changelog

All notable changes to Lumin CMS are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/) (while below 1.0, minor releases may include breaking changes).

## [0.1.0] - 2026-11-13

First public release. Download `lumin-cms-0.1.0.zip` from the GitHub release: it ships with `vendor/` and the built front end, so Composer and Node.js are not needed on the server.

### Installation

- Browser installer (`/install`): requirements check, database connection test (SQLite, MySQL/MariaDB, PostgreSQL), administrator account, site settings and optional sample content. Available in all admin languages.
- Command-line installer: `php artisan cms:install`, fully scriptable with options for unattended installs.
- The installer locks itself when it finishes and switches the site to production settings.
- Release package built by `scripts/build-release.sh` and published automatically from a `v*` tag.

### Visual editor

- Drag-and-drop page builder (GrapesJS) that outputs clean, semantic HTML without inline styles or generated IDs.
- Per-entry choice between the visual editor and a rich-text editor (TipTap).
- Global parts (header, footer) edited in the same editor and assigned to theme slots.
- Theme-provided blocks with settings dialogs and live preview.

### Custom fields

- Field-group builder with 35 field types: text, number, choice, date and time, color, media (image, file, gallery), WYSIWYG, oEmbed, links, relations (post object, relationship, taxonomy, user), OpenStreetMap map, layout elements and containers (Repeater, Group, Flexible Content, Clone).
- Conditional logic, location rules, server-side validation and nested containers.
- Password fields are stored encrypted.
- Field values are available in templates through `$content->field()` and through the `[field]` shortcode.

### Themes and templates

- Theme folders with a `theme.json` manifest, page templates, a template hierarchy and fallbacks to the default theme.
- Child themes, theme blocks, global parts, menu locations and a customizer with design tokens and live preview.
- Theme management in the admin: activate, export as ZIP, delete, and install from a validated ZIP archive (can be disabled with `CMS_THEME_UPLOADS=false`).
- Nested menus with drag-and-drop ordering and mega menus.

### SEO

- Title templates, meta descriptions, canonical URLs, robots directives, Open Graph, Twitter cards and JSON-LD.
- `sitemap.xml` and `robots.txt`.
- Snippet preview with focus-keyword checks.
- Redirects (301/302/307/308/410), automatic redirects on URL changes, a 404 monitor and a customizable 404 page.

### Content, media and users

- Pages, posts, custom content types and hierarchical or flat taxonomies, with status tabs, search and filters.
- Scheduled publishing.
- Media library with WebP conversion, configurable sizes, focal points, crops and responsive `<picture>` output.
- Seven roles, including two for external SEO agencies, with last-administrator protection and optional public registration.
- Admin panel in eleven languages.

### JSON importer

- Import and export of content types, taxonomies, field groups and content as one JSON file.
- Runs in a single transaction with a dry-run mode, so a failed import never leaves partial data.

### Analytics

- Google Analytics 4, Tag Manager and Meta Pixel with a cookie-consent banner.
- Optional dashboard widget with visits, sessions and top pages from the Google Analytics 4 Data API. It uses a Google service account whose key is stored encrypted.

[0.1.0]: https://github.com/chrzysztof/lumincms/releases/tag/v0.1.0
