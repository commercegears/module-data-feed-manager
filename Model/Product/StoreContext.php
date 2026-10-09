<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Product;

use Magento\Store\Api\Data\StoreInterface;

/**
 * Store-level data shared by all products of one feed run.
 */
class StoreContext
{
    /**
     * @param array<int, array{name: string, level: int, path_ids: int[], path: string}> $categories active categories below the store root
     * @param array<int, int> $googleMap category ID => Google taxonomy ID (only mapped categories)
     * @param array<int, string> $taxonomyPaths Google taxonomy ID => full path
     * @param array<int, string> $attributeSets attribute set ID => name
     * @param array<string, mixed> $storeVariables values for {{ store.* }}
     * @param array<string, mixed> $feedVariables values for {{ feed.* }}
     * @param string|null $brandAttribute attribute code read by {{ product.brand }} (null: none found)
     */
    public function __construct(
        public readonly StoreInterface $store,
        public readonly int $storeId,
        public readonly int $websiteId,
        public readonly string $baseCurrency,
        public readonly string $currency,
        public readonly float $rate,
        public readonly string $productMediaUrl,
        public readonly string $linkBaseUrl,
        public readonly string $placeholderUrl,
        public readonly array $categories,
        public readonly array $googleMap,
        public readonly array $taxonomyPaths,
        public readonly array $attributeSets,
        public readonly array $storeVariables,
        public readonly array $feedVariables,
        public readonly string $today,
        public readonly ?string $brandAttribute = null
    ) {
    }

    public function convert(?float $baseAmount): ?float
    {
        if ($baseAmount === null) {
            return null;
        }
        return round($baseAmount * $this->rate, 2);
    }

    public function imageUrl(?string $file): ?string
    {
        if ($file === null || $file === '' || $file === 'no_selection') {
            return null;
        }
        if (preg_match('#^https?://#i', $file)) {
            return $file;
        }
        return $this->productMediaUrl . '/' . ltrim($file, '/');
    }

    /**
     * Google taxonomy ID for a category: its own mapping or the nearest mapped parent.
     */
    public function googleCategoryFor(int $categoryId): ?int
    {
        if (isset($this->googleMap[$categoryId])) {
            return $this->googleMap[$categoryId];
        }
        $pathIds = $this->categories[$categoryId]['path_ids'] ?? [];
        for ($i = count($pathIds) - 1; $i >= 0; $i--) {
            if (isset($this->googleMap[$pathIds[$i]])) {
                return $this->googleMap[$pathIds[$i]];
            }
        }
        return null;
    }
}
