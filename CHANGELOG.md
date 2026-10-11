# Changelog

## 1.5.3 (2026-10-11)

### Fixed

- "Duplicate" in the feeds grid opened a 404 page: the link was sent with GET to a POST-only action.
  The action now asks for confirmation and posts the request.

## 1.5.2 (2026-10-10)

### Fixed

- Template editor: when the module's admin stylesheet is missing or out of date (for example static content not
  redeployed after an upgrade in production mode), the editor now stays a plain text field instead of drawing the
  line numbers and a second copy of the template over it.
- Template editor: typing in large templates (over 30,000 characters) is debounced instead of re-highlighting on
  every keystroke; Magento's "changed from default" tracking of the field works again; the "Line, column" hint no
  longer depends on the translation script being loaded first.
- `replace` with a map of pairs: the size check now counts the actual occurrences, so long descriptions are no
  longer refused (and the item skipped) when the result would stay well under the limit.

### Upgrade notes (1.5.0)

- Since 1.5.0 a single template value may be at most 1 MB and one item's output at most 8 MB, in previews and in
  feed generation. An item that exceeds a limit is skipped and the run ends with a warning in the feed log; the file
  stays valid.
- The preview size setting is limited to 1 to 20 products; a stored higher value is used as 20.

## 1.5.1 (2026-10-10)

### Fixed

- Feed edit page: the Save button had no click action of its own, so Magento also gave it a link to the store's
  base URL. Depending on timing, a click opened that page instead of saving. The button now only saves.
- Feeds grid: the main part of the Add Feed button opened the store's base URL instead of a new Google Shopping
  feed (its action was set under the key only the drop-down items read).

## 1.5.0 (2026-10-10)

### Added

- Template editor: syntax highlighting for template tags, expressions, filters, strings and comments, XML tags,
  attributes and comments, and JSON keys and values; line numbers; cursor line and column. Tab indents and Shift+Tab
  outdents (also for several selected lines); Esc then Tab moves to the next field. Unclosed `{{`, `{%` or `{#` are
  marked. The textarea stays the form field (undo, selection and saving unchanged). No external libraries.
- Preview from the page header (**Preview**) and the Content tab (**Preview Output**): renders the feed for the first
  matching products from the unsaved form values in a panel, highlighted with line numbers. A template error names
  its line and has a **Show line N in the template** button; an output validation error marks the line in the output.
  The Preview tab shows the same highlighted output.

### Changed

- Preview Items (`softaware_datafeed/general/preview_limit`): default 5 (was 10), at most 20 (was 100). Higher saved
  values are treated as 20.
- The preview stops after reading 500 products or after about 10 seconds when the template prints nothing for most
  products, and says so, instead of reading the whole catalogue.
- Runtime errors in a template (for example an invalid `regex_replace` pattern) and invalid item XML name the line
  (template line, or line of the item's output).

### Security

- A template could exhaust the server's memory or CPU (for example `{% set s = s ~ s %}` in a loop, `replace`,
  `join` or `regex_replace` building huge values, or deeply nested loops), crashing the PHP worker or the cron run
  and putting other sites on the server at risk. Template values are now limited to 1 MB, the output of one item,
  header or footer to 8 MB and loops to 1,000,000 steps per item; the limit is checked before a large value is
  built. Normal feeds are far below these limits.

## 1.4.0 (2026-10-09)

### Added

- Settings > Product Data > **Brand Attribute** (`softaware_datafeed/product_data/brand_attribute`): the product
  attribute used as the brand. Default: Automatic (the attribute `brand` if the store has one, otherwise
  `manufacturer`).
- Template variable `{{ product.brand }}`: the brand attribute's value (option label), falling back to the parent
  product's value for variants.

### Changed

- The Google Shopping, Meta, Pinterest and Microsoft presets send `{{ product.brand }}` as the brand and use the store
  name only when the product has no brand. Before, g:brand fell back to the store name whenever "manufacturer" was
  empty, even when the store keeps the brand in another attribute.
- Upgrade: existing feeds that still contain the unchanged preset expression for the brand are switched to
  `{{ product.brand }}` (data patch `UseBrandVariable`); edited templates and columns are not touched. Existing config
  paths are unchanged.

## 1.3.1 (2026-10-08)

### Added

- Product listing, FAQ and user guide (docs/)

## 1.3.0 (2026-10-07)

### Added

- Keyword search in the feed grid (name and file name; new full-text index on the feed table).
- Empty states: the feed grid and the generation log explain what they are for and link to the next step
  ("Create your first feed", "Go to Feeds") instead of showing an empty table.

### Changed

- The feed form uses proper vertical tabs on the left (General, Content, Product Filters, Schedule, Delivery,
  Preview) instead of a long page of sections that opened downwards. The Content tab shows the template (XML, JSON)
  or the columns (CSV, TXT) for the selected format, with the variables reference below it.
- Feed grid: the Status column says Enabled / Disabled; long file paths wrap and Items / Size are hidden by default (still under Columns), so the grid fits narrower
  screens.
- Google Category Mapping: "Save Mapping" is in the page action bar, the intro is shorter, category indentation
  uses the module stylesheet instead of inline styles, and the taxonomy import has a labelled file field and an
  "Import Taxonomy" button.
- Generation log: "Back to Feeds" button.
- Settings: split into General (always open) and Generation groups, every field shows its default and can be reset
  to it (config paths unchanged).
- Shorter, clearer help texts in the feed form.

## 1.2.0 (2026-10-06)

### Changed

- Requires `softaware/module-core` instead of `softaware/module-base`. The admin menu and ACL now sit under `Softaware_Core::core` ("Softaware"); roles that had access keep it (migrated by module-core). After updating all SoftAware modules, `softaware/module-base` can be removed.

## 1.1.0 (2026-10-06)

### Added
- SFTP host key check: the server's key fingerprint is saved on the first upload (or entered in the new
  "SFTP Host Key" field) and uploads stop before the password is sent if the key changes. Test Connection shows it.
- New feeds get a file name with a random part, so public feed URLs cannot be guessed.

### Security
- XML feeds can no longer contain XHTML, SVG, MathML, XSLT or XLink content or an `xml-stylesheet` instruction.
  Such a file, served from the store's domain, ran scripts in the browser (stored XSS for anyone opening the feed
  URL, including other admins). Affected items are skipped and logged; a header or footer with it stops publishing.
- Feed locations: hidden folders (e.g. `pub/.well-known/`) are refused, and outside `pub/media/feeds/` a feed can
  no longer overwrite an existing file it did not create (e.g. `pub/robots.txt`).
- The saved FTP/SFTP password is no longer sent when the host or protocol was changed in the form (Save and Test
  Connection); it has to be entered again.

### Fixed
- "Start anyway" on the generation page no longer replaces a generation that is still running (cron, CLI or another
  tab), which could mix two runs in one file.
- XML namespace declarations in single quotes (`xmlns:g='…'`) in the template header are recognised when items are
  checked.

## 1.0.0

- Feeds with store view, format (XML, JSON, CSV, delimited TXT), file location, currency, schedule and delivery
- Ready templates: Google Shopping (XML), Meta catalogue (CSV), Pinterest catalogue (CSV), Microsoft/Bing Shopping (TXT), generic JSON
- Sandboxed template language for XML/JSON and column mapper for CSV/TXT with delimiter, enclosure, encoding and line ending options
- Product variables for attributes, prices with/without tax, stock, URLs, images, categories, Google category, parent and variant data, bundle/grouped children and reviews
- Product filters: status, stock, type, attribute set, visibility, categories, attribute conditions, excluded SKUs; parent/variant export modes with item_group_id
- Google product taxonomy (en-GB bundled, other languages importable) and category mapping with inheritance
- Batched generation by cron (daily times or cron expression), admin progress page and CLI; preview of unsaved changes; output validation; generation log
- Optional SFTP/FTP/FTPS upload with encrypted password and connection test
