<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model;

use Magento\Framework\Model\AbstractModel;
use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Model\ResourceModel\Feed as FeedResource;

class Feed extends AbstractModel implements FeedInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'commercegears_datafeed_feed';

    protected function _construct(): void
    {
        $this->_init(FeedResource::class);
    }

    public function getName(): string
    {
        return (string)$this->getData(self::NAME);
    }

    public function setName(string $name): self
    {
        return $this->setData(self::NAME, $name);
    }

    public function isActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $active): self
    {
        return $this->setData(self::IS_ACTIVE, (int)$active);
    }

    public function getStoreId(): int
    {
        return (int)$this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getFormat(): string
    {
        return (string)($this->getData(self::FORMAT) ?: self::FORMAT_XML);
    }

    public function setFormat(string $format): self
    {
        return $this->setData(self::FORMAT, $format);
    }

    public function getFilename(): string
    {
        return (string)$this->getData(self::FILENAME);
    }

    public function setFilename(string $filename): self
    {
        return $this->setData(self::FILENAME, $filename);
    }

    public function getPath(): string
    {
        return (string)($this->getData(self::PATH) ?: 'pub/media/feeds/');
    }

    public function setPath(string $path): self
    {
        return $this->setData(self::PATH, $path);
    }

    public function getCurrencyCode(): ?string
    {
        $code = (string)$this->getData(self::CURRENCY_CODE);
        return $code !== '' ? $code : null;
    }

    public function setCurrencyCode(?string $code): self
    {
        return $this->setData(self::CURRENCY_CODE, $code ?: null);
    }

    public function getTemplate(): string
    {
        return (string)$this->getData(self::TEMPLATE);
    }

    public function setTemplate(?string $template): self
    {
        return $this->setData(self::TEMPLATE, $template);
    }

    public function getColumns(): array
    {
        return $this->decodeRows((string)$this->getData(self::COLUMNS), ['header', 'value', 'default']);
    }

    public function setColumns(array $columns): self
    {
        return $this->setData(self::COLUMNS, json_encode(array_values($columns), JSON_UNESCAPED_UNICODE));
    }

    public function getConditions(): array
    {
        return $this->decodeRows((string)$this->getData(self::FILTER_CONDITIONS), ['attribute', 'operator', 'value']);
    }

    public function setConditions(array $conditions): self
    {
        return $this->setData(self::FILTER_CONDITIONS, json_encode(array_values($conditions), JSON_UNESCAPED_UNICODE));
    }

    public function getParentChildMode(): string
    {
        return (string)($this->getData(self::PARENT_CHILD_MODE) ?: self::MODE_PARENT);
    }

    public function getScheduleType(): string
    {
        return (string)($this->getData(self::SCHEDULE_TYPE) ?: self::SCHEDULE_MANUAL);
    }

    public function isFtpEnabled(): bool
    {
        return (bool)$this->getData(self::FTP_ENABLED);
    }

    public function isDelimited(): bool
    {
        return in_array($this->getFormat(), [self::FORMAT_CSV, self::FORMAT_TXT], true);
    }

    public function getListValue(string $field): array
    {
        $value = $this->getData($field);
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,]+/', (string)$value) ?: [];
        }
        return array_values(array_filter(array_map('strval', $items), static fn ($v) => $v !== ''));
    }

    /**
     * @param string[] $keys
     * @return array<int, array<string, string>>
     */
    private function decodeRows(string $json, array $keys): array
    {
        if ($json === '') {
            return [];
        }
        $rows = json_decode($json, true);
        if (!is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean = [];
            foreach ($keys as $key) {
                $clean[$key] = (string)($row[$key] ?? '');
            }
            $result[] = $clean;
        }
        return $result;
    }
}
