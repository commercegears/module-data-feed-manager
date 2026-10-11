# Changelog

## 1.5.4 (2026-10-11)
- The main part of the Add Feed button works again and opens a blank feed.
- Preview says how many matching products are configurable and the item count of the last generated file.
- Generation log: an unfinished or unpublished run counts as an error; admin pages no longer show server paths.
- Feeds grid shows the public URL; Generate Now asks for confirmation; Meta quantity left empty when unknown.
- Settings are checked on the server; the log can be kept for 0 to 3650 days.

## 1.5.3 (2026-10-11)
- Duplicate in the feeds grid asks for confirmation and no longer opens a 404 page.

## 1.5.2 (2026-10-10)
- Template editor stays a plain field when its stylesheet is missing; large templates are debounced.
- The `replace` filter's size check counts real occurrences, so long descriptions are no longer skipped.

## 1.5.1 (2026-10-10)
- Save on the feed form only saves (it could open the store's home page instead).

## 1.5.0 (2026-10-10)
- Template editor with syntax highlighting, line numbers, cursor position and indenting with Tab.
- Preview panel from the page header renders unsaved changes and points to the line of an error.
- Preview Items: default 5, at most 20; the preview stops early when most products print nothing.
- Limits on template values, item output and loops protect the server from runaway templates.

## 1.4.0 (2026-10-09)
- New Brand Attribute setting and `{{ product.brand }}` variable, used by the ready templates.
- Existing feeds with the unchanged preset brand expression are switched to the new variable on upgrade.

## 1.3.1 (2026-10-08)
- Product listing, FAQ and user guide.

## 1.3.0 (2026-10-07)
- Keyword search in the feed grid; empty states for the feed grid and the generation log.
- Feed form with vertical tabs; Content tab shows the template or the columns for the chosen format.
- Settings split into General and Generation groups with defaults that can be restored.

## 1.2.0 (2026-10-06)
- Requires softaware/module-core instead of softaware/module-base; menu and ACL under Softaware.

## 1.1.0 (2026-10-06)
- SFTP host key check; new feeds get a file name with a random part.
- XML feeds cannot contain script-capable content (XHTML, SVG, XSLT and similar).
- Feeds cannot be written to hidden folders or over files they did not create.
- Start anyway no longer replaces a generation that is still running.

## 1.0.0
- Feeds in XML, JSON, CSV and TXT with ready templates for Google, Meta, Pinterest and Microsoft.
- Sandboxed template language, column mapper, product filters and variant export modes.
- Google taxonomy and category mapping; scheduled, admin and CLI generation with preview and log.
- Optional SFTP, FTP or FTPS upload with an encrypted password and connection test.
