# Changelog

## 2.0.1 (2026-10-12)
- Improvement: user guide screenshots and links updated for the CommerceGears name

## 2.0.0 (2026-10-11)
- Improvement: renamed to CommerceGears (new Composer package, admin menu and settings paths)

## 1.5.4 (2026-10-11)
- Fix: the main part of the Add Feed button works again and opens a blank feed.
- Fix: an unfinished or unpublished run counts as an error in the generation log.
- Improvement: preview shows how many matching products are configurable and the last file's item count.
- Improvement: Feeds grid shows the public URL; Generate Now asks for confirmation; admin hides server paths.
- Improvement: settings are checked when saved; the log can be kept for 0 to 3650 days.

## 1.5.3 (2026-10-11)
- Fix: Duplicate in the feeds grid no longer opens a 404 page and now asks for confirmation.

## 1.5.2 (2026-10-10)
- Fix: the template editor stays a plain field when its stylesheet is missing or out of date.
- Fix: the replace filter no longer skips long descriptions that stay well under the size limit.
- Improvement: typing in large templates stays smooth.

## 1.5.1 (2026-10-10)
- Fix: Save on the feed form only saves (it could open the store's home page instead).
- Fix: the main part of the Add Feed button no longer opens the store's home page.

## 1.5.0 (2026-10-10)
- New: template editor with syntax highlighting, line numbers, cursor position and indenting with Tab.
- New: preview panel in the page header shows unsaved changes and points to the line of an error.
- Improvement: Preview Items defaults to 5, at most 20; preview stops early when most products print nothing.
- Improvement: limits on template values, item output and loops protect the server from runaway templates.

## 1.4.0 (2026-10-09)
- New: Brand Attribute setting and product.brand template variable, used by the ready templates.
- Improvement: feeds with the unchanged preset brand expression switch to the new variable on upgrade.

## 1.3.1 (2026-10-08)
- New: product listing, FAQ and user guide.

## 1.3.0 (2026-10-07)
- New: keyword search in the feed grid; empty states for the feed grid and the generation log.
- Improvement: feed form with vertical tabs; Content shows the template or the columns for the format.
- Improvement: settings split into General and Generation groups with defaults that can be restored.

## 1.2.0 (2026-10-06)
- Improvement: requires CommerceGears Core instead of CommerceGears Base; menu and permissions move under "CommerceGears".

## 1.1.0 (2026-10-06)
- New: SFTP host key check; new feeds get a file name with a random part.
- Fix: XML feeds can no longer contain script-capable content (XHTML, SVG, XSLT and similar).
- Fix: feeds can no longer be written to hidden folders or over files they did not create.
- Fix: Start anyway no longer replaces a generation that is still running.

## 1.0.0 (2026-10-06)
- New: first release with XML, JSON, CSV and TXT feeds; templates for Google, Meta, Pinterest, Microsoft.
- New: sandboxed template language, column mapper, product filters and variant export modes.
- New: Google taxonomy and category mapping; scheduled, admin and command line generation with preview and log.
- New: optional SFTP, FTP or FTPS upload with an encrypted password and connection test.
