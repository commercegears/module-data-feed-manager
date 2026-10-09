<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Product;

use Softaware\DataFeed\Model\Config;

/**
 * The product attribute behind {{ product.brand }}: the one chosen in the settings, otherwise "brand" when the
 * store has such an attribute, otherwise "manufacturer".
 */
class BrandAttribute
{
    public const AUTO_CANDIDATES = ['brand', 'manufacturer'];

    public function __construct(
        private readonly Config $config,
        private readonly AttributeResolver $attributes
    ) {
    }

    public function getCode(): ?string
    {
        $configured = $this->config->getBrandAttribute();
        if ($configured !== '' && $this->attributes->isAttribute($configured)) {
            return $configured;
        }
        foreach (self::AUTO_CANDIDATES as $code) {
            if ($this->attributes->isAttribute($code)) {
                return $code;
            }
        }
        return null;
    }
}
