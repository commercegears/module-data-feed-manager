<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product attributes that can hold a brand (text, dropdown, multiple select), plus the automatic choice.
 */
class BrandAttribute implements OptionSourceInterface
{
    private const INPUTS = ['text', 'select', 'multiselect'];

    /** @var array<int, array{value: string, label: string}>|null */
    private ?array $options = null;

    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $options = [];
            $collection = $this->collectionFactory->create()
                ->addVisibleFilter()
                ->addFieldToFilter('frontend_input', ['in' => self::INPUTS]);
            foreach ($collection as $attribute) {
                $code = (string)$attribute->getAttributeCode();
                if ($code === 'sku') {
                    continue;
                }
                $options[$code] = (string)($attribute->getFrontendLabel() ?: $code) . ' (' . $code . ')';
            }
            asort($options, SORT_NATURAL | SORT_FLAG_CASE);
            $this->options = [['value' => '', 'label' => (string)__('Automatic: brand, otherwise manufacturer')]];
            foreach ($options as $code => $label) {
                $this->options[] = ['value' => (string)$code, 'label' => $label];
            }
        }
        return $this->options;
    }
}
