# Changelog

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
