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
- **Kinds, their fields, their grading scales and the wording of the shared
  catalog number field are declared in one place**, `Schema::get_kinds()`. The
  catalog number stays one meta key for every kind — only its label and
  placeholder change, so banknotes ask for a Pick number and coins for a KM
  number. Adding a kind is one array entry; the forms, detail view, CSV export
  and meta registration all derive from it. Resist adding kind-specific
  branches to templates.
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
- `src/class-numista.php` — the Numista v3 API client and the mapping from a
  catalogue entry onto item fields.
- `src/class-csv.php` — CSV export.
- `src/class-abilities.php` — read-only Abilities API lookups.
- `templates/` — one PHP template per route, plus partials prefixed with `_`
  (`_head.php`, `_foot.php`, `_item-card.php`, `_field-input.php`), which the
  PHPCS filename sniff is configured to skip.
- `assets/collectibles.css` — the whole UI, light and dark.

### Routes

Registered in `App::setup_routes()`, under `/collectibles/`:

- `` (index), `search`, `settings`
- `collection/new`, `collection/{id}`, `collection/{id}/edit`,
  `collection/{id}/export`
- `collection/{collection_id}/item/new`,
  `collection/{collection_id}/item/{id}`,
  `collection/{collection_id}/item/{id}/edit`

Build in-app URLs with `Collectibles\App::get_url( $path )` and asset URLs with
`App::get_asset_url( $relative )`.

### Numista lookups

Coins and banknotes can be filled in from the Numista catalogue. The public
catalogue pages sit behind a bot challenge, so the plugin uses the v3 API at
`api.numista.com`, which needs credentials the user registers for their own
Numista account: client name, client id and API key, kept in user meta and
edited at `/collectibles/settings/`. `COLLECTIBLES_NUMISTA_API_KEY` in
wp-config.php overrides the stored key.

A lookup reads two endpoints, because Numista splits the data in two: the
**type** says what the piece is (denomination, size, watermark, printer,
catalogue references), while an **issue** says which one you are holding (year,
mint letter, signatures, and a more precise catalogue number). Signatures and
mint letters are *only* on the issue. So a new piece costs two calls, and the
form offers an issue picker when a type has more than one.

The key is metered at 2000 calls a month, so treat every call as expensive:

- Answers are cached per endpoint and language for a year. Never cache them
  without an expiry — a non-expiring transient is autoloaded on every request.
- `Numista::record_call()` counts each request against a per-user monthly
  budget, and `request()` refuses once it is spent. Cache hits are free and keep
  working after the budget runs out; a call that never reached the quota (a
  transport error, or a key Numista rejects) is refunded.
- When testing, do not call the API. Seed the transients with a record that was
  already fetched — `wp eval` on the live site can dump one — and the whole form
  flow runs offline. The synthetic records in the mapping tests exercise
  `map_type()` for free.
- The documented base is `https://api.numista.com/v3` and `lang` officially
  takes `en`, `es` and `fr`, though `de` is served too.

Catalogue photos cannot be imported: `en.numista.com` answers a server-side
request for an image with the same bot challenge as the rest of the site, and a
real browser without Numista cookies gets it too, so hotlinking fails as well.
Photos are the collector's own, uploaded through the form.

Which catalogue number a kind files items under is declared as `codes` on the
kind's `catalog` entry (`P` for banknotes, `KM` for coins); other references in
the entry are appended to the item's notes.

### Storage model

No custom tables:

- `coll_collection` — a collection; meta `kind`, `currency`
- `coll_item` — an item, child of a collection via `post_parent`; one meta key
  per field, including `numista_id` for coins and banknotes, which links the
  item back to its catalogue entry
- `coll_tag` — the shared, flat tag taxonomy on items
- photos — ordinary attachments parented to the item. `photo_front` and
  `photo_back` meta point at the two named slots; the front doubles as the
  featured image, and `Item::get_photo_ids()` leads with front, then back, then
  the rest. Deleting a photo must go through `Item::forget_photo()` so a slot
  never points at a gap.

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
