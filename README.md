# Collectibles

- Contributors: akirk
- Tags: collection, inventory, coins, stamps, wp-app
- Requires at least: 6.0
- Requires PHP: 7.4
- Tested up to: 7.1
- Stable tag: 1.0.0
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Catalog what you collect — coins, stamps, banknotes, cards, records, books — each kind with its own fields, grading scale and totals.

## Description

Collectibles is a catalog for the things you keep in a drawer: coins, stamps,
banknotes, trading cards, records, books, comics, watches, brick sets, or whatever else.
Built on [WpApp](https://github.com/akirk/wp-app), so it runs as its own app at
`/collectibles/` instead of inside wp-admin.

### Collections of a kind

Every collection has a kind, and the kind decides which fields its items get. A
coin asks for denomination, mint, mint mark, composition, weight and diameter; a
stamp asks for perforation, watermark, gum and cancellation; a record asks for
artist, label, format, speed and sleeve condition. Ten kinds ship, including a
generic one for everything else.

### The right grading scale per kind

Coins grade Mint State to Poor, stamps Superb to Defective, cards Gem Mint 10
down to 1, records the Goldmine M/NM/VG+ scale, books As New to Poor.

### Fields every item shares

Status, condition, quantity, year, origin, catalog number, where it is stored,
when and from whom it was acquired, what it cost and what it is worth.

### More than one of the same thing

An item can hold several lots. A stack of the same note is not one homogeneous
thing — one copy can be uncirculated and two well used, bought at different
times for different money — so condition, piece count, price paid and estimated
value are recorded per lot, while what the piece is and where it comes from stay
on the item.

### Wishlists

An item's status can be *on the wishlist*, *ordered*, *duplicate*, *for sale* or
*sold*. Only what you actually hold counts towards the totals.

### Photos

Several per item, with a named front and back, stored as ordinary WordPress
attachments.

### Tags and search

Tags work across every collection, and a search looks at titles, notes, tags
*and* every recorded field value at once.

### Totals

Per collection and across all of them: entries, pieces held, what you paid, what
it is worth. Prices are recorded in the collection's currency, so totals never
mix currencies.

### Where things come from

Each item records an origin — an ISO country or one of the historic issuers such
as a defunct empire — and an Origins page shades a world map with the territories
your collection covers.

### Catalog lookups

Coins and banknotes can be filled in from the Numista catalogue using API
credentials you register for your own Numista account and enter under Settings.
Answers are cached and metered against a monthly budget, and no lookup happens
without credentials.

Brick sets support any brand, with fields for theme, parts per set, completeness,
box and instructions. LEGO sets can be filled in from [Rebrickable](https://rebrickable.com/api/)
using a personal API key entered under Settings. Enter a set number (e.g.
`10497` or `10497-1`) or a Rebrickable set link. The lookup fills in the name,
year, set number, part count and theme; review the form before saving. The set
number uses LEGO’s number (e.g. `10497`); the separate Rebrickable reference keeps
the variant (`10497-1`). Links to Rebrickable and LEGO.com open in a new tab or
window after a lookup, so you can find images to upload. On saved LEGO items,
the set number links to LEGO.com and the Rebrickable reference links to its
catalogue page. LEGO product and instructions links follow your WordPress
language and region, with a language-region override under Settings when
LEGO.com does not support the inferred code. Rebrickable publishes a rate limit
of one request per second on average, rather than a monthly quota. Responses
are cached for a year. Prices and photos are recorded manually. The shared
quantity field counts copies of the set, not the parts inside it. Completeness
and packaging describe the item; use separate entries if copies differ in those
respects. `COLLECTIBLES_REBRICKABLE_API_KEY` in wp-config.php can supply a site key.

### CSV export

One file per collection, for an insurance list or a spreadsheet.

### Abilities

Read-only lookups (`collectibles/list-collections`, `collectibles/search-items`,
`collectibles/get-item`) are exposed through the Abilities API, so an assistant
can answer "do I already have this one?".

### Storage

No custom tables. A collection is a `coll_collection` post, an item is a
`coll_item` post parented to it, field values are post meta, tags are the
`coll_tag` taxonomy, and photos are attachments parented to the item. Deleting a
collection deletes its items and their photos.

Development of this plugin happens [on GitHub](https://github.com/akirk/collectibles).
Pull requests welcome.

## Installation

1. Upload the `collectibles` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Visit `/collectibles/` on your site

## AI assistant abilities

When the WordPress Abilities API is available, the plugin registers five abilities,
exposed through the authenticated Abilities REST API:

- `collectibles/list-collections`: list your collections and their totals.
- `collectibles/search-items`: search existing items by text, collection and status.
- `collectibles/get-item`: read an item's fields, notes, tags and lots.
- `collectibles/lookup-catalog-entry`: fetch metadata using a collection ID and a
  `reference` (a Rebrickable set number/link or Numista type number/link). Uses the
  configured user's credentials and existing provider caches. Numista results
  include issue choices; repeat with `issue_id` to select one.
- `collectibles/add-item`: create an item from a collection ID, `title`, a unique
  `request_id`, and optional `values`, `notes`, comma-separated `tags` and `lots`.
  All field values are strings. Reusing the same request ID and payload returns
  the previously created item; changing the payload with that ID returns an error.

For example, an assistant handling “Add LEGO set 10497 to my LEGO collection” can
list collections, search for an existing set, look up `10497` in the brick-set
collection, and pass the returned title, values, notes and tags to `add-item`
with that collection ID and a new request ID. The result includes the item ID
and its app URL. The assistant needs a connection that discovers and executes
WordPress abilities; registration alone does not connect an external assistant.

Creation defaults to one owned set/copy. Personal condition, completeness and
prices remain unspecified unless supplied. The four lot fields belong in `lots`,
for example `[{"quantity":"1","condition_grade":"sealed","purchase_price":"100"}]`.
Prices are per set/copy in the collection currency; `part_count` is the number of
parts in each set. Collection-scoped lookup, search and creation require permission
to edit the collection; reading an item requires permission to edit that item.

Run the offline integration checks as a user who can edit posts:

```sh
wp --url=alex.kirk.at --user=<user-id> eval-file tests/abilities.php
composer lint
```

The checks use temporary posts, remove them afterward, and block all HTTP requests.

## Frequently Asked Questions

### Does this plugin create custom tables?

No. A collection is a post, an item is a post parented to it, field values are
post meta, tags are a taxonomy and photos are ordinary attachments. Delete the
plugin and your database is as slim as before.

### Who can see my collection?

The app requires a login and the `edit_posts` capability, and collections and
items belong to their author.

### Can I add a kind of collectible that is not shipped?

Yes. Everything the app knows about a kind lives in one array entry in
`src/class-schema.php`: a label, an icon, its extra fields and its condition
scale. Add an entry and the forms, detail views, CSV export and meta
registration follow along — no template changes needed.

### Do I need a Numista account?

Only if you want catalog lookups for coins and banknotes. Everything else works
without one. Register a client on your own Numista account and enter the client
name, client id and API key under Settings.

### Can I get my data back out?

Every collection has a CSV export, with one row per lot.

## Screenshots

1. Starting out: the kinds of thing the app knows how to collect, each bringing the fields it will ask for.
2. A collection of banknotes, with the entries it holds, the pieces counted, and the search, status, tag and order filters above them.

## Changelog

### 1.0.0

- Initial release.
