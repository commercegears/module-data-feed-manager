# Changelog

## 1.0.0

- Feeds with store view, format (XML, JSON, CSV, delimited TXT), file location, currency, schedule and delivery
- Ready templates: Google Shopping (XML), Meta catalogue (CSV), Pinterest catalogue (CSV), Microsoft/Bing Shopping (TXT), generic JSON
- Sandboxed template language for XML/JSON and column mapper for CSV/TXT with delimiter, enclosure, encoding and line ending options
- Product variables for attributes, prices with/without tax, stock, URLs, images, categories, Google category, parent and variant data, bundle/grouped children and reviews
- Product filters: status, stock, type, attribute set, visibility, categories, attribute conditions, excluded SKUs; parent/variant export modes with item_group_id
- Google product taxonomy (en-GB bundled, other languages importable) and category mapping with inheritance
- Batched generation by cron (daily times or cron expression), admin progress page and CLI; preview of unsaved changes; output validation; generation log
- Optional SFTP/FTP/FTPS upload with encrypted password and connection test
