# Coupon Importer

A WordPress plugin that unifies coupon/deal imports from multiple affiliate networks into [WP Coupons and Deals (WPCD)](https://wordpress.org/plugins/wp-coupons-and-deals/), replacing what used to be several separate, single-network importer plugins.

Built for a high-volume Brazilian affiliate coupon platform managing 500+ live pages across multiple advertiser networks.

## The problem

Affiliate coupon sites typically pull deals from spreadsheets or CSV feeds exported by each network (AWIN, Rakuten, CJ, Admitad, CityAds, Amazon, etc.). Every network has its own:

- Link format (some need a template with placeholders, some hand you a ready-made tracking link, Amazon just needs a tag appended)
- Publisher/affiliate ID, sometimes overridden per advertiser
- CSV/feed quirks (delimiter, column order, date formats)

Without a unified system, this becomes a pile of one-off scripts and manual link-building, and a small template typo can break affiliate revenue tracking site-wide.

## What it does

- **Network-level link templates** — define once per network (e.g. AWIN's `{MID}` / `{AFFID}` / `{URL}` placeholder format), reuse for every store on that network.
- **Per-shop overrides** — a single store can override the network's publisher ID or have its own fully custom link template (useful for networks like Admitad where some advertisers hand out a unique cloaked tracking link per store).
- **Amazon-specific handling** — appends `?tag=` automatically, no template needed.
- **"Link direct" networks** — for feeds where the affiliate link already comes pre-built in the CSV (e.g. Lomadee, Afilio), the importer skips template logic entirely.
- **Flexible CSV ingestion** — accepts a pasted CSV, an uploaded file, or up to 5 saved Google Sheets URLs (auto-converted to CSV export links). Auto-detects `,` vs `;` delimiter and multiple date formats.
- **Safe re-imports** — matches existing coupons by code + store taxonomy term, so re-running an import updates existing entries instead of duplicating them. Coupons that were manually edited in wp-admin are flagged and skipped on title overwrite.
- **Dry-run preview** — see exactly what would be created/updated/ignored, with line-by-line debug output, before committing to the database.
- **Migration-aware activation hook** — on first activation, migrates shop data from two predecessor plugins (an AWIN-only crawler and a generic CSV importer) into the new unified schema, deduplicating by name.

## Structure

```
coupon-importer/
├── coupon-importer.php       Plugin bootstrap, activation/migration hook
├── admin/
│   ├── menu.php               Registers the 3 admin pages
│   └── pages/
│       ├── sync.php           Settings + import UI (AJAX-driven, with progress bar)
│       ├── shops.php          CRUD for stores (network, MID, publisher ID, aliases, custom link template)
│       └── networks.php       CRUD for affiliate networks (link template, publisher ID, link-direct flag)
├── includes/
│   ├── helpers.php            Link-building logic, shop/network lookups, default network presets
│   ├── processor.php          CSV/feed parsing + WPCD post creation/update
│   └── ajax.php                wp_ajax handlers for preview + sync
└── uninstall.php              Cleans up plugin options on uninstall
```

## Requirements

- WordPress with [WP Coupons and Deals (WPCD)](https://wordpress.org/plugins/wp-coupons-and-deals/) active (the plugin writes to its `wpcd_coupons` post type and `wpcd_coupon_vendor` taxonomy)
- PHP 7.4+

## Notes

This is a real plugin extracted from a production site, shared as a code sample. Default network presets in `includes/helpers.php` use placeholder publisher IDs (`YOUR_AWIN_PUBLISHER_ID`, etc.) — replace with your own affiliate account identifiers before use.

## License

MIT
