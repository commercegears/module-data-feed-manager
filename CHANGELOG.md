# Changelog

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
