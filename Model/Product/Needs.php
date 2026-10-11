<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Product;

/**
 * What a template uses, so only the required data is loaded for each batch.
 */
class Needs
{
    private const BASE_ATTRIBUTES = [
        'name', 'url_key', 'image', 'small_image', 'thumbnail', 'status', 'visibility', 'price',
        'special_price', 'special_from_date', 'special_to_date', 'tax_class_id',
    ];

    private const NOT_SELECTABLE = [
        'category_ids', 'media_gallery', 'gallery', 'tier_price', 'quantity_and_stock_status',
        'sku', 'type_id', 'attribute_set_id', 'created_at', 'updated_at', 'entity_id', 'has_options',
        'required_options',
    ];

    /**
     * @param string[] $attributeCodes EAV attributes to select
     */
    public function __construct(
        public readonly array $attributeCodes,
        public readonly bool $gallery,
        public readonly bool $categories,
        public readonly bool $reviews,
        public readonly bool $tierPrices,
        public readonly bool $children,
        public readonly bool $parents
    ) {
    }

    /**
     * @param string[] $referencedKeys property names used in the template(s)
     * @param string[] $allAttributeCodes all product attribute codes
     */
    public static function fromKeys(array $referencedKeys, array $allAttributeCodes, string $parentChildMode): self
    {
        $keys = array_flip($referencedKeys);
        $has = static function (array $names) use ($keys): bool {
            foreach ($names as $name) {
                if (isset($keys[$name])) {
                    return true;
                }
            }
            return false;
        };

        $codes = array_values(array_diff(
            array_unique(array_merge(self::BASE_ATTRIBUTES, array_intersect($referencedKeys, $allAttributeCodes))),
            self::NOT_SELECTABLE
        ));

        $expand = $parentChildMode !== 'parent';
        return new self(
            $codes,
            $has(['images', 'additional_images', 'all_images']),
            $has(['category', 'category_id', 'category_ids', 'category_path', 'categories', 'category_paths',
                'google_category', 'google_category_id']),
            $has(['reviews_count', 'rating', 'rating_5']),
            $has(['tier_prices']),
            $expand || $has(['variants', 'children']),
            $expand || $has(['parent', 'item_group_id', 'url', 'image', 'images', 'small_image', 'thumbnail',
                'all_images', 'category', 'category_path', 'google_category', 'google_category_id'])
        );
    }
}
