# Data Feed Manager for Magento 2: FAQ

**Which channels are supported?**
Any channel that reads an XML, JSON, CSV or delimited TXT file from a URL or an upload. Ready templates are included
for Google Shopping (RSS 2.0 XML with the `g:` namespace), Meta catalogue (CSV), Pinterest catalogue (CSV),
Microsoft/Bing Shopping (tab-delimited TXT) and a generic JSON feed. For other channels, start from a blank feed or
change a template.

**Do I need to know PHP to change a feed?**
No. XML and JSON feeds use a small template language: `{{ product.name | truncate(150) }}` prints a value,
`{% for product in products %}` marks the repeated item, and `{% if %}` adds conditions. CSV and TXT feeds use a
column mapper with a header, a value and a default per column. The full list of variables and filters is shown in the
feed form.

**Can a broken template take my feed offline?**
No. Each item is checked on its own, and the finished file is validated (XML parsed, JSON decoded) before it replaces
the old one. Broken items are skipped and logged; an invalid file is not published and the previous file stays in
place. Files are replaced in one step, so a channel never downloads a half-written file.

**How large can my catalogue be?**
Products are loaded in batches (Batch Size, default 250), so memory use does not grow with the catalogue. Scheduled
feeds run in their own cron group, and "Generate Now" in the admin runs one batch per request with a progress bar.

**How are configurable products exported?**
You choose per feed: parents only, variants only (each variant with `item_group_id` set to the parent SKU), or both.
Variants without their own URL or image use the parent's.

**Which prices are used?**
Guest prices (customer group "NOT LOGGED IN") from Magento's price index: regular, final, special, minimum and maximum
price and tier prices, each also with or without tax, converted to the feed's currency. Tax is calculated for the
store's default tax destination. If a feed shows outdated prices, reindex prices.

**How does the Google product category get into the feed?**
Under **CommerceGears > Data Feed Manager > Google Category Mapping** you map your store categories to Google's product
taxonomy with autocomplete. Categories without a mapping inherit the nearest mapped parent. The English (en-GB)
taxonomy is installed with the module; other languages can be imported. The mapping is global, not per feed.

**Can feeds be uploaded to a server?**
Yes, by SFTP, FTP or FTPS after each generation, with a **Test Connection** button. The password is stored encrypted,
and for SFTP the server's host key is saved on the first upload and checked on every later one. Please note: FTP
upload was tested against a local FTP server, but SFTP has not yet been tested against a real SFTP server. FTPS uses
PHP's FTP extension, which does not verify the server's TLS certificate, so prefer SFTP. A failed upload marks the run
as a warning; the local file is still updated.

**Are feed files public?**
Files below `pub/` have a public URL, and new feeds get a file name with a random part so the URL cannot be guessed.
Anyone who has the URL can read the file, so put feeds with private data (cost, stock levels) below `var/` and upload
them by SFTP instead.

**Does it support Multi-Source Inventory (MSI)?**
Stock values come from the default stock. Per-source quantities are not exported.

**Does it change the storefront?**
No. The module only works in the admin, by cron and on the command line, so it works with Luma, Hyvä and any other
theme.

**Can I generate feeds from the command line?**
Yes: `bin/magento commercegears:feed:generate <id>` (or `--all`), `commercegears:feed:list`, `commercegears:feed:preview <id>`
and `commercegears:feed:taxonomy-import` for another taxonomy language.
