<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Api\Data;

/**
 * Product feed definition.
 */
interface FeedInterface
{
    public const FEED_ID = 'feed_id';
    public const NAME = 'name';
    public const IS_ACTIVE = 'is_active';
    public const STORE_ID = 'store_id';
    public const FORMAT = 'format';
    public const FILENAME = 'filename';
    public const PATH = 'path';
    public const CURRENCY_CODE = 'currency_code';
    public const TEMPLATE = 'template';
    public const COLUMNS = 'columns';
    public const CSV_DELIMITER = 'csv_delimiter';
    public const CSV_ENCLOSURE = 'csv_enclosure';
    public const CSV_ENCODING = 'csv_encoding';
    public const CSV_INCLUDE_HEADER = 'csv_include_header';
    public const CSV_LINE_ENDING = 'csv_line_ending';
    public const FILTER_TYPES = 'filter_types';
    public const FILTER_ATTRIBUTE_SETS = 'filter_attribute_sets';
    public const FILTER_VISIBILITY = 'filter_visibility';
    public const FILTER_ENABLED_ONLY = 'filter_enabled_only';
    public const FILTER_IN_STOCK_ONLY = 'filter_in_stock_only';
    public const FILTER_CATEGORIES = 'filter_categories';
    public const FILTER_CATEGORY_MODE = 'filter_category_mode';
    public const FILTER_CONDITIONS = 'filter_conditions';
    public const EXCLUDE_SKUS = 'exclude_skus';
    public const PARENT_CHILD_MODE = 'parent_child_mode';
    public const SCHEDULE_TYPE = 'schedule_type';
    public const SCHEDULE_TIMES = 'schedule_times';
    public const SCHEDULE_CRON = 'schedule_cron';
    public const NEXT_RUN_AT = 'next_run_at';
    public const FTP_ENABLED = 'ftp_enabled';
    public const FTP_PROTOCOL = 'ftp_protocol';
    public const FTP_HOST = 'ftp_host';
    public const FTP_PORT = 'ftp_port';
    public const FTP_USERNAME = 'ftp_username';
    public const FTP_PASSWORD = 'ftp_password';
    public const FTP_PATH = 'ftp_path';
    public const FTP_PASSIVE = 'ftp_passive';
    public const GENERATED_AT = 'generated_at';
    public const GENERATED_ITEMS = 'generated_items';
    public const GENERATED_SIZE = 'generated_size';
    public const GENERATED_STATUS = 'generated_status';
    public const GENERATED_MESSAGE = 'generated_message';

    public const FORMAT_XML = 'xml';
    public const FORMAT_JSON = 'json';
    public const FORMAT_CSV = 'csv';
    public const FORMAT_TXT = 'txt';

    public const MODE_PARENT = 'parent';
    public const MODE_CHILD = 'child';
    public const MODE_BOTH = 'both';

    public const SCHEDULE_MANUAL = 'manual';
    public const SCHEDULE_TIMES_DAILY = 'times';
    public const SCHEDULE_CRON_EXPR = 'cron';

    public function getId();

    public function getName(): string;

    public function setName(string $name): self;

    public function isActive(): bool;

    public function setIsActive(bool $active): self;

    public function getStoreId(): int;

    public function setStoreId(int $storeId): self;

    public function getFormat(): string;

    public function setFormat(string $format): self;

    public function getFilename(): string;

    public function setFilename(string $filename): self;

    public function getPath(): string;

    public function setPath(string $path): self;

    public function getCurrencyCode(): ?string;

    public function setCurrencyCode(?string $code): self;

    public function getTemplate(): string;

    public function setTemplate(?string $template): self;

    /**
     * CSV/TXT column map.
     *
     * @return array<int, array{header: string, value: string, default: string}>
     */
    public function getColumns(): array;

    /**
     * @param array<int, array{header: string, value: string, default: string}> $columns
     */
    public function setColumns(array $columns): self;

    /**
     * Attribute conditions.
     *
     * @return array<int, array{attribute: string, operator: string, value: string}>
     */
    public function getConditions(): array;

    /**
     * @param array<int, array{attribute: string, operator: string, value: string}> $conditions
     */
    public function setConditions(array $conditions): self;

    public function getParentChildMode(): string;

    public function getScheduleType(): string;

    public function isFtpEnabled(): bool;

    public function isDelimited(): bool;

    /**
     * @return string[]
     */
    public function getListValue(string $field): array;
}
