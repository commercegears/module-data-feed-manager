# Softaware Data Feed Manager

`softaware/module-data-feed-manager` (Magento module `Softaware_DataFeed`)

Exports product data as XML, JSON, CSV or delimited TXT feeds for Google Shopping, Meta (Facebook/Instagram),
Pinterest, Microsoft/Bing Shopping, price comparison sites and ERPs. Feeds are generated in batches (cron, admin or
CLI), written to a local file with a public URL and can be uploaded by FTP, FTPS or SFTP.

Documentation: [User guide](docs/user-guide.md) · [FAQ](docs/faq.md)

## Features

- **Feeds** (Softaware > Data Feed Manager > Feeds): name, store view, format, file name and directory, currency,
  enabled switch, schedule, product filters, delivery. Grid with file link, last result, item count and size;
  mass enable, disable and delete; duplicate.
- **Ready templates** ("Add Feed" split button): Google Shopping (RSS 2.0 XML with the `g:` namespace),
  Meta catalogue (CSV), Pinterest catalogue (CSV), Microsoft/Bing Shopping (tab-delimited TXT), generic JSON.
- **Template editor** for XML/JSON: a small, sandboxed template language (no PHP):
  - `{{ product.name | strip_tags | truncate(150) }}` prints a value, escaped for XML or JSON automatically
  - `{% for product in products %} … {% endfor %}` marks the repeated item (exactly one, at top level)
  - `{% if … %} {% elseif … %} {% else %} {% endif %}`, `{% for image in product.images %}`, `{% set x = … %}`,
    `{# comments #}`; `loop.index`, `loop.first`, `loop.last`
  - operators `and or not == != < > <= >= in ~ + - * /`, fallback `a ?? b`, ternary `a ? b : c`
  - every item is checked on its own (XML well-formed / JSON valid); broken items are skipped and logged
- **Column mapper** for CSV/TXT: header, value (same template syntax, or static text) and default per column,
  drag to reorder; delimiter (comma, semicolon, tab, pipe), enclosure (double, single, none), encoding (UTF-8,
  UTF-8 with BOM, ISO-8859-1, Windows-1252), header row, LF/CRLF.
- **Variables**: any product attribute (store-view value, option labels; `product.raw.<code>` for the stored value),
  prices (`regular_price`, `final_price`, `special_price`, `min_price`, `max_price`, each also `_incl_tax` /
  `_excl_tax`, `tier_prices`, `on_sale`) converted to the feed currency, stock (`qty`, `is_in_stock`,
  `is_salable`, `availability`, `availability_text`), `url`, images (`image`, `images`, `all_images`,
  `small_image`, `thumbnail`, `placeholder`), categories (`category`, `category_path`, `categories`,
  `category_paths`, `google_category_id`, `google_category`), `brand` (the Brand Attribute setting, falls back to
  the parent), `parent.*` and `item_group_id` for variants,
  `variants` (configurable) and `children` (configurable, bundle, grouped), reviews (`reviews_count`, `rating`,
  `rating_5`), `store.*`, `feed.*`, `now`. The full list with descriptions is in the feed form (Content tab) under
  "Variables and Filters".
- **Filters** (template): strip_tags, truncate, upper, lower, capitalize, title, trim, clean, number_format, round,
  price, default, if, replace, regex_replace, prepend, append, join, split, first, last, slice, count, unique,
  date, html_decode, url_encode, json, cdata, raw, escape.
- **Product filters**: enabled only, in stock only, product types, attribute sets, visibility, categories
  (include or exclude, with subcategories), attribute conditions (`= != > < >= <= contains, does not contain, is one
  of, is not one of, is empty, is not empty`; option labels are accepted for dropdowns), SKUs to exclude.
- **Configurable products**: parents only, variants only (with `item_group_id`), or both. Variants without their own
  URL or image fall back to the parent's.
- **Google category mapping** (Softaware > Data Feed Manager > Google Category Mapping): map store categories to the
  Google product taxonomy with autocomplete; categories inherit the nearest mapped parent. The en-GB taxonomy is
  imported on installation; other languages can be uploaded (IDs are the same in every language).
- **Generation**: batched by product ID (memory use does not grow with catalogue size), atomic file replacement,
  final validation (streamed XML check, JSON check). An invalid file is not published, the previous file stays.
  - cron: per feed daily times or a cron expression (store view timezone), own cron group `softaware_datafeed`
  - admin: "Generate Now" runs batch by batch in the browser with a progress bar
  - CLI: see below
  - **preview** (Preview tab of the feed form) renders the first items of the unsaved form values and validates them
  - **generation log** (Softaware > Data Feed Manager > Generation Log): start time, trigger, status, duration, items,
    errors with messages, file size
- **Delivery**: local file (public URL when below `pub/`), optional upload by SFTP, FTP or FTPS (password stored
  encrypted, "Test Connection" button). Upload failures mark the run as a warning; the local file is still updated.
  - **SFTP host key check**: the server's key fingerprint (`SHA256:…`) is saved with the feed on the first upload
    (or entered in "SFTP Host Key"). If the server presents another key later, the upload stops before the password
    is sent. Clear the field when the server was really replaced. Changing the host or port clears it too.
  - The saved password is only reused for the same host and protocol; after changing either, enter it again.

The feed form (Softaware > Data Feed Manager > Feeds) has tabs on the left: General, Content (the template for XML
and JSON or the columns for CSV and TXT, depending on the format), Product Filters, Schedule, Delivery (FTP / SFTP)
and Preview. The feed grid has a keyword search (name and file name).

## Configuration

Stores > Configuration > Softaware > Data Feed Manager (groups General, Generation and Product Data):

| Setting | Path | Default |
|---|---|---|
| Enabled | `softaware_datafeed/general/enabled` | Yes |
| Batch Size | `softaware_datafeed/general/batch_size` | 250 |
| Preview Items | `softaware_datafeed/general/preview_limit` | 10 |
| Keep Generation Log For (Days) | `softaware_datafeed/general/log_retention_days` | 30 |
| Brand Attribute | `softaware_datafeed/product_data/brand_attribute` | Automatic: `brand` if the store has it, otherwise `manufacturer` |

**Brand**: `{{ product.brand }}` reads the Brand Attribute (option labels for dropdowns); a variant without a value
uses its parent's. The Google Shopping, Meta, Pinterest and Microsoft presets send it as the brand and use the store
name only when the product has no brand. Feeds created from these presets before 1.4.0 are updated on upgrade
(only the unchanged preset expression is replaced; edited templates are left alone).

When disabled, nothing is generated (cron, admin and CLI); existing files stay in place.

ACL: Softaware > Data Feed Manager > Manage Feeds (Generate Feeds, Delete Feeds), Google Category Mapping,
Generation Log, Settings.

## CLI

```
bin/magento softaware:feed:list
bin/magento softaware:feed:generate <id> [<id> …]
bin/magento softaware:feed:generate --all
bin/magento softaware:feed:preview <id> [--limit=3]
bin/magento softaware:feed:taxonomy-import [path/to/taxonomy-with-ids.de-DE.txt]
```

## File locations

Feeds may be written below `pub/` (public; below `pub/media/` the URL is the media URL) or below `var/` (not public,
use FTP upload). `pub/static/`, `pub/errors/` and hidden folders such as `pub/.well-known/` are not allowed. File names
may only contain letters, digits, `.`, `-` and `_` and must end in `.xml`, `.json`, `.csv`, `.txt` or `.tsv` to match
the format. Partial files and job state are kept in `var/softaware_datafeed/` while a feed is generated.

- New feeds get a file name with a random part (e.g. `google_shopping_k3v9q2xa.xml`), so the URL cannot be guessed.
  Public feed files can still be read by anyone who has the URL; put feeds with private data (cost, stock levels)
  below `var/` and upload them by SFTP.
- Outside `pub/media/feeds/` a feed can only write a new file: a location where another file already exists (for
  example `pub/robots.txt` or `pub/sitemap.xml`) is refused when the feed is saved.
- XML feeds may not contain XHTML, SVG, MathML, XSLT or XLink content or an `xml-stylesheet` instruction, because
  browsers would run such a file as a web page on the store's domain. Items with such content are skipped and logged;
  a header or footer with it stops the file from being published.

## Compatibility

- Magento Open Source / Adobe Commerce 2.4.7 and later (developed and tested on 2.4.9), PHP 8.2 to 8.5
- Works with the legacy stock tables (single source / default stock). With MSI multi-source setups, stock values
  come from the default stock index.
- Admin only; no storefront output, so it is theme independent (Luma, Hyvä and others).

## Limitations

- Prices are for guests (customer group "NOT LOGGED IN") from the price index; tax is calculated for the store's
  default tax destination. Reindex prices if feeds show outdated prices.
- Attribute conditions apply to the selected products, not to variants added through the configurable product mode
  (variants are only filtered by status and, if set, stock).
- In "variants only" mode a simple product that belongs to a configurable product is exported only through that
  parent.
- The Google category mapping is global (not per store view or feed).
- The template language is deliberately small; there are no custom PHP functions in templates.
- `{{`, `{%` and `}}` cannot appear inside string literals of a template expression.
- CSV values are written as they are (no protection against spreadsheet formulas such as `=…`), because channels
  read the file, not a spreadsheet program. Do not open feeds built from untrusted text in a spreadsheet.
- FTPS uses PHP's FTP extension, which does not verify the server's TLS certificate. Prefer SFTP.
