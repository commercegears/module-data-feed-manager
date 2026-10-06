<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Module settings (Stores > Configuration > Softaware > Data Feed Manager).
 */
class Config
{
    private const XML_ENABLED = 'softaware_datafeed/general/enabled';
    private const XML_BATCH_SIZE = 'softaware_datafeed/general/batch_size';
    private const XML_PREVIEW_LIMIT = 'softaware_datafeed/general/preview_limit';
    private const XML_LOG_DAYS = 'softaware_datafeed/general/log_retention_days';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED);
    }

    public function getBatchSize(): int
    {
        return max(10, min(5000, (int)$this->scopeConfig->getValue(self::XML_BATCH_SIZE) ?: 250));
    }

    public function getPreviewLimit(): int
    {
        return max(1, min(100, (int)$this->scopeConfig->getValue(self::XML_PREVIEW_LIMIT) ?: 10));
    }

    public function getLogRetentionDays(): int
    {
        return max(0, (int)$this->scopeConfig->getValue(self::XML_LOG_DAYS));
    }
}
