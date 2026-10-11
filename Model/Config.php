<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Module settings (Stores > Configuration > CommerceGears > Data Feed Manager).
 */
class Config
{
    private const XML_ENABLED = 'commercegears_datafeed/general/enabled';
    private const XML_BATCH_SIZE = 'commercegears_datafeed/general/batch_size';
    private const XML_PREVIEW_LIMIT = 'commercegears_datafeed/general/preview_limit';
    private const XML_LOG_DAYS = 'commercegears_datafeed/general/log_retention_days';
    private const XML_BRAND_ATTRIBUTE = 'commercegears_datafeed/product_data/brand_attribute';

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
        return max(1, min(20, (int)$this->scopeConfig->getValue(self::XML_PREVIEW_LIMIT) ?: 5));
    }

    public function getLogRetentionDays(): int
    {
        return max(0, (int)$this->scopeConfig->getValue(self::XML_LOG_DAYS));
    }

    /**
     * Attribute code chosen for {{ product.brand }}; empty means automatic (brand, otherwise manufacturer).
     */
    public function getBrandAttribute(): string
    {
        return trim((string)$this->scopeConfig->getValue(self::XML_BRAND_ATTRIBUTE));
    }
}
