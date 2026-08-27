# Collectibles

A WordPress app for cataloging collections — coins, stamps, banknotes, trading
cards, records, books, or whatever else you keep in a drawer. Built on
[WpApp](https://github.com/akirk/wp-app), so it runs as its own app at
`/collectibles/` instead of inside wp-admin.

## What it does

- **Collections of a kind.** Every collection has a kind, and the kind decides
  which fields its items get. A coin asks for denomination, mint, mint mark,
  composition, weight and diameter; a stamp asks for perforation, watermark,
  gum and cancellation; a record asks for artist, label, format, speed and
  sleeve condition. Seven kinds ship, including a generic one.
- **The right grading scale per kind.** Coins grade Mint State to Poor, stamps
  Superb to Defective, cards Gem Mint 10 down to 1, records the Goldmine
  M/NM/VG+ scale, books As New to Poor.
- **Fields every item shares.** Status, condition, quantity, year, origin,
  catalog number, where it is stored, when and from whom it was acquired, what
  it cost and what it is worth.
- **Wishlists.** An item's status can be *on the wishlist*, *ordered*,
  *duplicate*, *for sale* or *sold*. Only what you actually hold counts towards
  the totals.
- **Photos.** Several per item, one of them the main one, stored as ordinary
  WordPress attachments.
- **Tags** across every collection, and a search that looks at titles, notes,
  tags *and* every recorded field value at once.
- **Totals** per collection and across all of them: entries, pieces held, what
  you paid, what it is worth.
- **CSV export** per collection, for insurance lists or a spreadsheet.
- **Abilities** for read-only lookups (`collectibles/list-collections`,
  `collectibles/search-items`, `collectibles/get-item`) so an assistant can
  answer "do I already have this one?".

## Installing

```bash
composer install
```

Then activate the plugin and visit `/collectibles/`. The app requires a login
and the `edit_posts` capability; items and collections are per author.

## Adding a kind of collectible

Everything the app knows about a kind lives in one array entry in
`src/class-schema.php`: a label, an icon, its extra fields, and its condition
scale. Add an entry and the forms, detail views, CSV export and meta
registration follow along — no template changes needed.

## Storage

No custom tables. A collection is a `coll_collection` post, an item is a
`coll_item` post parented to it, field values are post meta, tags are the
`coll_tag` taxonomy, and photos are attachments parented to the item. Deleting
a collection deletes its items and their photos.

## Development

```bash
composer lint     # PHPCS, WordPress Coding Standards
composer format   # PHPCBF autofix
```
