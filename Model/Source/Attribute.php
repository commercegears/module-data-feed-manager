<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Product attributes usable in filter conditions.
 */
class Attribute implements OptionSourceInterface
{
    private const STATIC_FIELDS = [
        'sku' => 'SKU',
        'type_id' => 'Product Type (code)',
        'attribute_set_id' => 'Attribute Set (ID)',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
        'entity_id' => 'Product ID',
    ];

    private const EXCLUDED = ['sku', 'category_ids', 'media_gallery', 'gallery', 'tier_price', 'quantity_and_stock_status',
        'options_container', 'custom_layout_update', 'custom_layout_update_file', 'page_layout', 'custom_design'];

    /** @var array<int, array{value: string, label: string}>|null */
    private ?array $options = null;

    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $options = [];
            foreach (self::STATIC_FIELDS as $code => $label) {
                $options[$code] = (string)__($label) . ' (' . $code . ')';
            }
            $collection = $this->collectionFactory->create()->addVisibleFilter();
            foreach ($collection as $attribute) {
                $code = (string)$attribute->getAttributeCode();
                if (in_array($code, self::EXCLUDED, true) || isset($options[$code])) {
                    continue;
                }
                $label = (string)($attribute->getFrontendLabel() ?: $code);
                $options[$code] = $label . ' (' . $code . ')';
            }
            asort($options, SORT_NATURAL | SORT_FLAG_CASE);
            $this->options = [];
            foreach ($options as $code => $label) {
                $this->options[] = ['value' => $code, 'label' => $label];
            }
        }
        return $this->options;
    }
}
