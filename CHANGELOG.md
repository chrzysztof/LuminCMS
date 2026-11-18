# Changelog

All notable changes to Lumin CMS are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/) (while below 1.0, minor releases may include breaking changes).

## [Unreleased]

## [0.4.0] - 2026-11-18

### Added

- Visual editor: the "Paragraph" block is now a rich-text block. Its content is edited in the block properties with the same rich-text editor as in posts (headings, lists, links, images, tables, alignment) and shows on the canvas live; the HTML goes through the same sanitizer as post content. Paragraphs already on a page keep their inline editing.
- Visual editor: a delete (trash) button on every layer in the Layers panel, shown on hover.
- Visual editor: optional "Custom styles (advanced)" for any selected element — background color/image, text color, padding, margin, radius, max-width, min-height, per device (desktop/tablet/phone) — with a warning that they can slow down loading and make the look inconsistent with the theme. Presets stay the default path. Styles are validated against a whitelist and output as shared `lm-x-*` classes in a single `<style id="lm-local-styles">` (no inline `style` attributes). New `css_cache` column on contents (run `php artisan migrate`).
- Theme authors: layouts of custom themes should print `app(\App\Cms\Builder\LocalCss::class)->css()` in `<head>` to support custom styles.

### Fixed

- The SEO snippet preview in the content editor and the generated meta description no longer show raw `[block ...]` shortcodes for pages built from theme blocks; blocks are skipped and custom-field values are filled in, so a page made only of blocks falls back to the site description.

### Changed

- Visual editor: back, title and status, device switcher, undo/redo, templates, history, preview and save/publish now share one white top bar, and the canvas starts below it so nothing covers the page being edited.
- Visual editor: the tools panel (Blocks, Properties, Layers) sits in the bottom-left corner, and element outlines on the canvas (hover, selection, guides) are 3 px thick.
- The map block now uses a place picker instead of typing coordinates: search an address, click the map or drag the marker; the zoom is remembered. Existing map blocks must be set again (the old latitude/longitude settings are no longer used).

## [0.3.1] - 2026-11-17

### Fixed

- The default theme's post template shows the lead paragraph (excerpt) only when it is filled in, so posts without an excerpt no longer render an empty line under the title.
- The release package for 0.3.0 was never built because its tag was missing the `v` prefix. 0.3.1 is the first installable package of the 0.3 series (it includes everything listed under 0.3.0); install or update to 0.3.1 instead of 0.3.0.

## [0.3.0] - 2026-11-17

### Added

- Theme Builder (Appearance → Templates): design in the visual editor how single items, archives and category/tag pages look. Each content type, archive and taxonomy can be assigned a template built from a starter design; without one the theme's default view is used (a page template chosen in the theme still wins). New "Templates" blocks: post content, date and categories, archive heading and archive items (with pagination); title, excerpt and featured image come from element bindings. Deleting a part unassigns it from templates. Themes can ship starter designs as `parts/template-single.html` and `parts/template-archive.html`, and a `templated` view.
- Pagination in the post list block: turn on "Pagination" to split the list into pages (`?strona=2`) with Previous / numbered / Next links. It works with the visitor filter (choosing a filter returns to page 1) and needs no JavaScript.
- Display conditions in the visual editor: show an element only to logged-in or logged-out visitors, only when a custom field is filled or empty, or only in a chosen period (from/to date). Conditions are evaluated when the page is displayed (nesting works) and an invalid condition hides the element; the editor always shows it.
- Theme block settings can be linked to custom fields of the page (or the title, excerpt and featured image): the field value replaces the block setting on the page and an empty field falls back to the setting. Works for text, link and image fields of any theme block, e.g. a video block fed from a "video URL" field.
- Built-in content fields in editor bindings: besides custom fields, an image can be bound to the page's featured image, and text elements to the page title or excerpt (`[field name="_featured" format="image"]`, `_title`, `_excerpt`). Works with the fallback value, so an empty featured image simply renders nothing.
- "Post list" block (Dynamic content category): lists published content of any type with filters set in the editor (category or tag, order, number), cards or list layout with 2-4 columns, optional featured image, date and excerpt, and the option to skip the current page. An optional visitor filter shows category buttons above the list and filters server-side (`?filtr=slug`, no JavaScript). Theme block fields can now take their options from the site (`choices_source`: `content_types`, `taxonomies`, `terms`), and block templates receive the current content as `$current`.
- Custom fields in blocks: in the visual editor select a heading, paragraph, button, link or image and choose a custom field of the page in "Custom field" (text, HTML content, link address or image). The value is filled in when the page is displayed, so changing the field updates the page without re-saving it; text left in the editor is the fallback for an empty field. The `[field]` shortcode got formats (`text`, `html`, `url`, `image`), a fallback value (`[field name="x"]fallback[/field]`) and now outputs rich values (links, selects, image fields, rich text). Generated HTML stays clean: only the shortcode is stored, no editor attributes.
- Widgets (new "Widgets" category in the visual editor): video (YouTube with the privacy-friendly domain, Vimeo with Do Not Track, or an MP4 file), tabs (keyboard accessible), animated counters (final numbers are shown without JavaScript), gallery with a lightbox (native `<dialog>`, arrow keys and Escape), logo grid and an OpenStreetMap map. They are theme blocks rendered on the server, so embeds work although the content filter strips `iframe` and scripts. Themes can now ship a `script.js` next to a block's `style.css`; scripts are bundled into `/theme-blocks.js` and loaded with `defer`.
- Visual editor: right-click context menu (duplicate, copy, paste, copy and paste style, move up/down, select parent, delete) and keyboard shortcuts: Ctrl/⌘+D duplicate, Alt+↑/↓ move, Ctrl/⌘+↑ select parent, Ctrl/⌘+Alt+C / V copy and paste style.
- Update notifications and one-click updates (Settings → Updates, administrators only). The system checks GitHub Releases twice a day (and on demand with "Check now"), shows a banner in the panel when a newer version exists and lists the release notes. Installing downloads the release package, verifies its SHA-256 checksum, extracts it safely, switches on maintenance mode, replaces the code keeping a backup, runs migrations and clears caches; any error restores the previous version. `.env`, `storage`, the database and custom themes are never touched. Also available from the terminal: `php artisan cms:check-updates` and `php artisan cms:update`. Git checkouts and read-only hosts get manual instructions instead. Set `CMS_UPDATE_CHECKS=false` to disable all update requests or `CMS_SELF_UPDATE=false` to keep notifications only. Release packages now contain a `VERSION` file and a `.sha256` checksum is published next to the ZIP.
- Ready-made headers and footers: five headers (logo left and menu right, classic with button, with contact bar, centered, dark) and four footers (four columns, with call to action, simple, dark centered) in Appearance → Parts, with live previews. A chosen design opens in the visual editor, so everything in it can be changed. New theme blocks: site logo, navigation menu, social links and copyright. Themes declare designs in `part_presets` and `parts/{slot}-{key}.html`.
- The visual editor canvas now loads the active theme's stylesheets, so pages look the same as on the public site.
- Site identity: choose a logo and a favicon in SEO settings. The logo replaces the site name in the theme header, the favicon (also used as Apple touch icon) is added to every page, the logo is included in structured data and a new `[site_logo]` shortcode places it in header parts and content.
- Visual editor: save any selected element as a reusable block ("Save as block"). Saved blocks appear in a "My blocks" category on every page and can be managed (deleted) from the block panel.
- Visual editor: version history. Every save and publish is recorded, the editor autosaves unsaved work every 30 seconds without touching the live page, offers to recover it after a crash and lets you restore any earlier version (up to 50 saves and 15 autosaves per page are kept).
- Visual editor style panel: spacing (padding and margin), text alignment and size, columns and column gap, vertical alignment, background, rounded corners, shadow, border, narrow content width and per-device visibility. Settings are per device (desktop, tablet, phone) and produce clean utility classes (`lm-pt-4`, `lm-md-cols-2`, `lm-sm-pt-2`, `lm-hide-mobile`) without inline styles.
- Visual editor library: ten ready-made sections (hero, features, steps, numbers, pricing, testimonials, FAQ, call to action, contact) in the block panel and a page library (landing, company, pricing, contact) under "Templates" in the toolbar.
- `npm run build:utilities` regenerates the utility classes from `resources/js/lib/builder/utilities.json`.
- Rich-text editor: icon toolbar with a "more tools" row, block format, highlight, sub/superscript, text alignment, images from the media library, tables, code blocks and a link panel.

### Fixed

- Shortcode parser: a self-closing shortcode (`[name /]`) no longer swallows text up to a later closing tag of the same name.
- SEO descriptions are built from rendered content, so shortcodes no longer appear as raw `[...]` in meta descriptions.

## [0.2.0] - 2026-11-16

### Added

- Content types can be created, edited and deleted in the admin (Structure → Content types) and are imported and exported with their URL prefix and capability type.
- Optional Google Analytics 4 widget on the dashboard (service account, key stored encrypted).
- Hexadecimal colour picker for colour fields and the theme customizer.
- Passkey sign-in button below the login form.
- Screenshots in the README.

### Changed

- New default theme look: sticky header, footer, hero, cards, article and archive styles. Posts and text pages now show their title, date, categories and excerpt.
- The starter home page is built in the visual editor (hero, three cards, call to action) and translated into every admin language.
- Page editor reworked: text (TipTap) or visual editing per entry, excerpt under the content, headings moved up.
- Admin sidebar: collapse button next to the logo, "View site" link under Dashboard instead of the banner; the language picker lives in Settings → Appearance only.
- The login form sits on a white card.

### Fixed

- 404 and 410 pages are translated; dates in the panel follow the panel language.

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

[0.4.0]: https://github.com/chrzysztof/lumincms/releases/tag/v0.4.0
[0.3.1]: https://github.com/chrzysztof/lumincms/releases/tag/v0.3.1
[0.3.0]: https://github.com/chrzysztof/lumincms/releases/tag/v0.3.0
[0.2.0]: https://github.com/chrzysztof/lumincms/releases/tag/v0.2.0
[0.1.0]: https://github.com/chrzysztof/lumincms/releases/tag/v0.1.0
