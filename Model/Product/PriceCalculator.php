<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Product;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;

/**
 * Price values in the feed currency, optionally with or without tax (default tax destination of the store).
 */
class PriceCalculator
{
    public const TYPES = ['price', 'regular_price', 'final_price', 'special_price', 'min_price', 'max_price'];

    public function __construct(private readonly CatalogHelper $catalogHelper)
    {
    }

    /**
     * @param string $type one of self::TYPES
     * @param string|null $tax null (as entered in the catalogue), "incl" or "excl"
     */
    public function get(Product $product, BatchData $batch, StoreContext $store, string $type, ?string $tax): ?float
    {
        $base = $this->getBase($product, $batch, $store, $type);
        if ($base === null) {
            return null;
        }
        if ($tax !== null) {
            $base = (float)$this->catalogHelper->getTaxPrice(
                $product,
                $base,
                $tax === 'incl',
                null,
                null,
                null,
                $store->storeId,
                null,
                false
            );
        }
        return $store->convert($base);
    }

    private function getBase(Product $product, BatchData $batch, StoreContext $store, string $type): ?float
    {
        $id = (int)$product->getId();
        $index = $batch->prices[$id] ?? null;
        $attributePrice = is_numeric($product->getData('price')) ? (float)$product->getData('price') : null;
        $min = $index['min_price'] ?? null;
        switch ($type) {
            case 'price':
            case 'regular_price':
                $price = $index['price'] ?? $attributePrice;
                return $price ? $price : ($min ?: $price);
            case 'final_price':
                $price = $index['final_price'] ?? null;
                if ($price === null) {
                    // Not in the price index (e.g. index not yet rebuilt): attribute price and special price.
                    $price = $attributePrice;
                    $special = $this->getActiveSpecialPrice($product, $store);
                    if ($special !== null) {
                        $price = $price === null ? $special : min($special, $price);
                    }
                }
                return $price ? $price : ($min ?: $price);
            case 'special_price':
                return $this->getActiveSpecialPrice($product, $store);
            case 'min_price':
                return $min ?? $attributePrice;
            case 'max_price':
                return $index['max_price'] ?? $attributePrice;
        }
        return null;
    }

    public function getActiveSpecialPrice(Product $product, StoreContext $store): ?float
    {
        $special = $product->getData('special_price');
        if (!is_numeric($special)) {
            return null;
        }
        $from = substr((string)$product->getData('special_from_date'), 0, 10);
        $to = substr((string)$product->getData('special_to_date'), 0, 10);
        if (($from !== '' && $store->today < $from) || ($to !== '' && $store->today > $to)) {
            return null;
        }
        return (float)$special;
    }

    /**
     * Tier prices for guests, in the feed currency.
     *
     * @return array<int, array{qty: float, price: float}>
     */
    public function getTierPrices(Product $product, BatchData $batch, StoreContext $store): array
    {
        $regular = $this->getBase($product, $batch, $store, 'regular_price');
        $result = [];
        foreach ($batch->tierPrices[(int)$product->getId()] ?? [] as $tier) {
            if (!$tier['all_groups'] && $tier['customer_group_id'] !== 0) {
                continue;
            }
            if ($tier['percentage'] !== null && $regular !== null) {
                $price = $regular * (1 - $tier['percentage'] / 100);
            } elseif ($tier['value'] !== null) {
                $price = $tier['value'];
            } else {
                continue;
            }
            $result[] = ['qty' => $tier['qty'], 'price' => (float)$store->convert($price)];
        }
        return $result;
    }
}
