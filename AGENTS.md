# AGENTS.md

## Project Overview

A WordPress plugin powered by WpApp (`akirk/wp-app`) for cataloging
collections of physical objects: coins, stamps, banknotes, trading cards,
records, books, and a generic catch-all kind.

- Plugin slug / text domain: `collectibles`
- Namespace: `Collectibles\` (classes live in `class-<kebab>.php` files per
  WordPress convention; the autoloader in `collectibles.php` bridges the two)
- Requires PHP 7.4+, WordPress 6.0+
- Base app URL path: `/collectibles/`
- Access: `require_login`, capability `edit_posts`

## Domain Notes

- A **collection** groups items of one **kind**. The kind is the pivot of the
  whole app: it decides which extra fields the items carry and which condition
  scale they are graded on.
- An **item** is one physical piece (or a stack of identical ones — it has a
  quantity). Items are children of a collection via `post_parent`.
- **Kinds, their fields and their grading scales are declared in one place**,
  `Schema::get_kinds()`. Adding a kind is one array entry; the forms, detail
  view, CSV export and meta registration all derive from it. Resist adding
  kind-specific branches to templates.
- Items of every kind share one post type, so post meta is registered on the
  *union* of all kinds' fields (`Schema::get_all_fields()`). Two kinds may
  reuse a key on purpose — coins and banknotes both have a `denomination`.
- A stored condition slug always belongs to some kind's scale. If a collection
  changes kind, old values stay in the database and
  `Schema::get_grade_label()` falls back to the raw slug.
- Only statuses in `Schema::get_owned_statuses()` count towards money and piece
  totals; a wishlist entry is not a possession.
- Prices are recorded per collection currency, not per item, so totals never
  mix currencies.

## Architecture

- `collectibles.php` — bootstrap: vendor autoload, class-file autoloader,
  `plugins_loaded` / activation / deactivation hooks.
- `src/class-app.php` — `App extends WpApp\BaseApp`. Builds the `WpApp`
  instance, registers post types, taxonomy and meta, enqueues the stylesheet on
  app requests, declares routes and menu items, and cascades deletions.
- `src/class-schema.php` — the kind definitions: labels, icons, fields, grades,
  plus the item statuses.
- `src/class-collection.php` / `src/class-item.php` — post types, meta
  registration, sanitizing, reading and querying.
- `src/class-csv.php` — CSV export.
- `src/class-abilities.php` — read-only Abilities API lookups.
- `templates/` — one PHP template per route, plus partials prefixed with `_`
  (`_head.php`, `_foot.php`, `_item-card.php`, `_field-input.php`), which the
  PHPCS filename sniff is configured to skip.
- `assets/collectibles.css` — the whole UI, light and dark.

### Routes

Registered in `App::setup_routes()`, under `/collectibles/`:

- `` (index), `search`
- `collection/new`, `collection/{id}`, `collection/{id}/edit`,
  `collection/{id}/export`
- `collection/{collection_id}/item/new`,
  `collection/{collection_id}/item/{id}`,
  `collection/{collection_id}/item/{id}/edit`

Build in-app URLs with `Collectibles\App::get_url( $path )` and asset URLs with
`App::get_asset_url( $relative )`.

### Storage model

No custom tables:

- `coll_collection` — a collection; meta `kind`, `currency`
- `coll_item` — an item, child of a collection via `post_parent`; one meta key
  per field
- `coll_tag` — the shared, flat tag taxonomy on items
- photos — ordinary attachments parented to the item, the featured image being
  the main one

Meta is registered with `show_in_rest` and a per-post `auth_callback` that
checks `edit_post`.

### Querying

`Item::query()` runs one `get_posts()` narrowed by collection (or by the
current user's collections) and then filters and sorts in PHP. That is
deliberate: a personal catalog is small, and it lets the free-text search cover
titles, notes, tags and every meta value in one pass, which stacked meta
queries cannot do. If a catalog ever grows past a few thousand items, that is
the place to revisit.

`Item::save_values()` writes the whole field set for a kind and deletes the
meta rows for empty values, so it expects a complete form submission, not a
partial patch.

## Tooling

- WP-CLI is available as `wp`. The relevant site is a multisite blog; use
  `wp --url=alex.kirk.at ...`.
- Composer scripts: `composer lint` (PHPCS), `composer format` (PHPCBF),
  `composer make-pot` / `update-po` / `make-mo` / `i18n`.
- PHPCS prefixes whitelisted for `WordPress.NamingConventions.PrefixAllGlobals`:
  `coll`, `collectibles`, `Collectibles`. Template variables are therefore all
  `$coll_…`.
- `phpcs.xml.dist` is clean; keep it that way.

## Style

- Brass `#a9762b` on warm paper, with verdigris `#3e6f63` for links and
  positive states. Both themes are defined in `assets/collectibles.css`.
