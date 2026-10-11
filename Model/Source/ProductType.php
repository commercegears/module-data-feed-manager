<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Source;

use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Data\OptionSourceInterface;

class ProductType implements OptionSourceInterface
{
    public function __construct(private readonly Type $productType)
    {
    }

    public function toOptionArray(): array
    {
        $result = [];
        foreach ($this->productType->getOptionArray() as $value => $label) {
            $result[] = ['value' => (string)$value, 'label' => (string)$label];
        }
        return $result;
    }
}
