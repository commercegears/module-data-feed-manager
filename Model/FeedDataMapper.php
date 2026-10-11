<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Model\Generator\FileLocator;
use CommerceGears\DataFeed\Model\Generator\Uploader;

/**
 * Converts between admin form data and the feed model.
 */
class FeedDataMapper
{
    public const PASSWORD_PLACEHOLDER = '******';

    private const SCALAR_FIELDS = [
        FeedInterface::NAME, FeedInterface::FORMAT, FeedInterface::FILENAME, FeedInterface::CURRENCY_CODE,
        FeedInterface::TEMPLATE, FeedInterface::CSV_DELIMITER, FeedInterface::CSV_ENCLOSURE, FeedInterface::CSV_ENCODING,
        FeedInterface::CSV_LINE_ENDING, FeedInterface::FILTER_CATEGORY_MODE, FeedInterface::EXCLUDE_SKUS,
        FeedInterface::PARENT_CHILD_MODE, FeedInterface::SCHEDULE_TYPE, FeedInterface::SCHEDULE_TIMES,
        FeedInterface::SCHEDULE_CRON, FeedInterface::FTP_PROTOCOL, FeedInterface::FTP_HOST, FeedInterface::FTP_USERNAME,
        FeedInterface::FTP_PATH, FeedInterface::FTP_HOST_KEY,
    ];

    private const FLAG_FIELDS = [
        FeedInterface::IS_ACTIVE, FeedInterface::CSV_INCLUDE_HEADER, FeedInterface::FILTER_ENABLED_ONLY,
        FeedInterface::FILTER_IN_STOCK_ONLY, FeedInterface::FTP_ENABLED, FeedInterface::FTP_PASSIVE,
    ];

    private const LIST_FIELDS = [
        FeedInterface::FILTER_TYPES, FeedInterface::FILTER_ATTRIBUTE_SETS, FeedInterface::FILTER_VISIBILITY,
        FeedInterface::FILTER_CATEGORIES,
    ];

    public function __construct(
        private readonly EncryptorInterface $encryptor,
        private readonly FileLocator $fileLocator
    ) {
    }

    /**
     * Applies posted form data to the feed.
     *
     * @param array<string, mixed> $data
     */
    public function apply(FeedInterface $feed, array $data): void
    {
        /** @var Feed $feed */
        foreach (self::SCALAR_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_scalar($data[$field]) ? (string)$data[$field] : '';
                $feed->setData($field, $field === FeedInterface::TEMPLATE ? $value : trim($value));
            }
        }
        foreach (self::FLAG_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $feed->setData($field, $this->toFlag($data[$field]));
            }
        }
        foreach (self::LIST_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $values = is_array($data[$field]) ? $data[$field] : explode(',', (string)$data[$field]);
                $values = array_values(array_unique(array_filter(array_map(
                    static fn ($v) => trim((string)$v),
                    $values
                ), static fn ($v) => $v !== '')));
                $feed->setData($field, $values ? implode(',', $values) : null);
            }
        }
        if (array_key_exists(FeedInterface::STORE_ID, $data)) {
            $feed->setData(FeedInterface::STORE_ID, (int)$data[FeedInterface::STORE_ID]);
        }
        if (array_key_exists(FeedInterface::PATH, $data)) {
            $feed->setData(FeedInterface::PATH, $this->fileLocator->normalisePath((string)$data[FeedInterface::PATH]));
        }
        if (array_key_exists(FeedInterface::FTP_PORT, $data)) {
            $port = (int)$data[FeedInterface::FTP_PORT];
            $feed->setData(FeedInterface::FTP_PORT, $port > 0 ? $port : null);
        }
        if (array_key_exists(FeedInterface::CURRENCY_CODE, $data)) {
            $feed->setData(FeedInterface::CURRENCY_CODE, ((string)$data[FeedInterface::CURRENCY_CODE]) ?: null);
        }
        if (array_key_exists(FeedInterface::FTP_HOST_KEY, $data)) {
            $hostKey = trim((string)$feed->getData(FeedInterface::FTP_HOST_KEY));
            $feed->setData(FeedInterface::FTP_HOST_KEY, $hostKey === '' ? null : Uploader::normaliseFingerprint($hostKey));
        }
        // A saved fingerprint belongs to the server it was seen on: a new host or port starts afresh.
        $origin = $feed->getOrigData();
        if ($feed->getId() && is_array($origin)
            && (strtolower((string)$feed->getData(FeedInterface::FTP_HOST)) !== strtolower((string)($origin[FeedInterface::FTP_HOST] ?? ''))
                || (int)$feed->getData(FeedInterface::FTP_PORT) !== (int)($origin[FeedInterface::FTP_PORT] ?? 0))
            && (string)$feed->getData(FeedInterface::FTP_HOST_KEY) === (string)($origin[FeedInterface::FTP_HOST_KEY] ?? '')
        ) {
            $feed->setData(FeedInterface::FTP_HOST_KEY, null);
        }
        if (array_key_exists(FeedInterface::FTP_PASSWORD, $data)) {
            $password = (string)$data[FeedInterface::FTP_PASSWORD];
            if ($password === '') {
                $feed->setData(FeedInterface::FTP_PASSWORD, null);
            } elseif ($password !== self::PASSWORD_PLACEHOLDER) {
                $feed->setData(FeedInterface::FTP_PASSWORD, $this->encryptor->encrypt($password));
            }
        }
        // The admin form does not post dynamic rows at all when every row was removed, so a full form post
        // (recognised by the format field) without rows means "no rows"; partial updates leave rows untouched.
        $isFullForm = array_key_exists(FeedInterface::FORMAT, $data);
        if ($isFullForm || array_key_exists('columns', $data)) {
            $feed->setColumns($this->rows($data['columns'] ?? [], ['header', 'value', 'default'], 'value'));
        }
        if ($isFullForm || array_key_exists('conditions', $data)) {
            $feed->setConditions($this->rows($data['conditions'] ?? [], ['attribute', 'operator', 'value'], 'attribute'));
        }
    }

    /**
     * The saved FTP/SFTP password is only kept ("******" posted) while the host and protocol stay the same.
     * Otherwise it could be sent to another server, or in clear text after a switch from SFTP to FTP.
     *
     * @param array<string, mixed> $data posted form data
     * @throws LocalizedException
     */
    public function assertSavedPasswordReusable(FeedInterface $feed, array $data): void
    {
        /** @var Feed $feed */
        if (!$feed->getId() || !$feed->getData(FeedInterface::FTP_PASSWORD)
            || !array_key_exists(FeedInterface::FTP_PASSWORD, $data)
            || (string)$data[FeedInterface::FTP_PASSWORD] !== self::PASSWORD_PLACEHOLDER
        ) {
            return;
        }
        $changed = false;
        if (array_key_exists(FeedInterface::FTP_HOST, $data)) {
            $host = strtolower(trim(is_scalar($data[FeedInterface::FTP_HOST]) ? (string)$data[FeedInterface::FTP_HOST] : ''));
            $changed = $host !== '' && $host !== strtolower(trim((string)$feed->getData(FeedInterface::FTP_HOST)));
        }
        if (array_key_exists(FeedInterface::FTP_PROTOCOL, $data)) {
            $protocol = is_scalar($data[FeedInterface::FTP_PROTOCOL]) ? (string)$data[FeedInterface::FTP_PROTOCOL] : '';
            $changed = $changed
                || ($protocol ?: 'sftp') !== ((string)$feed->getData(FeedInterface::FTP_PROTOCOL) ?: 'sftp');
        }
        if ($changed) {
            throw new LocalizedException(__(
                'Enter the password again: a saved password is only used with the host and protocol it was entered for.'
            ));
        }
    }

    /**
     * Form data for the admin form.
     *
     * @return array<string, mixed>
     */
    public function toFormData(FeedInterface $feed): array
    {
        /** @var Feed $feed */
        $data = $feed->getData();
        foreach (self::LIST_FIELDS as $field) {
            $data[$field] = $feed->getListValue($field);
        }
        foreach (self::FLAG_FIELDS as $field) {
            if (isset($data[$field])) {
                $data[$field] = (string)(int)$data[$field];
            }
        }
        $columns = $feed->getColumns();
        unset($data[FeedInterface::COLUMNS], $data[FeedInterface::FILTER_CONDITIONS]);
        $data['columns'] = [];
        foreach ($columns as $index => $column) {
            $data['columns'][] = $column + ['record_id' => $index, 'position' => $index];
        }
        $data['conditions'] = [];
        foreach ($feed->getConditions() as $index => $condition) {
            $data['conditions'][] = $condition + ['record_id' => $index];
        }
        $data[FeedInterface::FTP_PASSWORD] = $feed->getData(FeedInterface::FTP_PASSWORD) ? self::PASSWORD_PLACEHOLDER : '';
        $data[FeedInterface::CURRENCY_CODE] = (string)$feed->getCurrencyCode();
        return $data;
    }

    /**
     * Preset/default values in form shape.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function presetToFormData(array $values): array
    {
        $data = $values;
        foreach ([FeedInterface::COLUMNS => 'columns'] as $source => $target) {
            if (isset($values[$source]) && is_array($values[$source])) {
                $data[$target] = [];
                foreach (array_values($values[$source]) as $index => $row) {
                    $data[$target][] = $row + ['record_id' => $index, 'position' => $index];
                }
            }
        }
        if (!isset($data['columns'])) {
            $data['columns'] = [];
        }
        $data['conditions'] = [];
        foreach (self::FLAG_FIELDS as $field) {
            if (isset($data[$field])) {
                $data[$field] = (string)(int)$data[$field];
            }
        }
        return $data;
    }

    private function toFlag(mixed $value): int
    {
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
        }
        return $value ? 1 : 0;
    }

    /**
     * @param string[] $keys
     * @return array<int, array<string, string>>
     */
    private function rows(mixed $rows, array $keys, string $required): array
    {
        if (!is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !empty($row['delete'])) {
                continue;
            }
            $clean = [];
            foreach ($keys as $key) {
                $clean[$key] = is_scalar($row[$key] ?? null) ? (string)$row[$key] : '';
            }
            if (trim($clean[$required]) === '' && trim($clean[$keys[0]]) === '') {
                continue;
            }
            $result[] = ['position' => (int)($row['position'] ?? count($result)), 'row' => $clean];
        }
        usort($result, static fn ($a, $b) => $a['position'] <=> $b['position']);
        return array_column($result, 'row');
    }
}
