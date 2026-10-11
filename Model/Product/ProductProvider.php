<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Api\Data\FeedInterface;

/**
 * Selects the products of a feed (filters) and returns them batch by batch as template contexts,
 * with configurable children expanded according to the parent/child mode.
 */
class ProductProvider
{
    public const OPERATORS = [
        'eq' => '=',
        'neq' => '!=',
        'gt' => '>',
        'lt' => '<',
        'gteq' => '>=',
        'lteq' => '<=',
        'like' => 'contains',
        'nlike' => 'does not contain',
        'in' => 'is one of (comma separated)',
        'nin' => 'is not one of (comma separated)',
        'null' => 'is empty',
        'notnull' => 'is not empty',
    ];

    private const STATIC_FIELDS = ['sku', 'type_id', 'attribute_set_id', 'created_at', 'updated_at', 'entity_id'];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly DataLoader $dataLoader,
        private readonly AttributeResolver $attributeResolver,
        private readonly PriceCalculator $priceCalculator
    ) {
    }

    /**
     * Number of products matching the feed filters (before children are expanded).
     *
     * @throws LocalizedException
     */
    public function count(FeedInterface $feed, StoreContext $store): int
    {
        return (int)$this->createFilteredCollection($feed, $store, ['name'])->getSize();
    }

    /**
     * Matching configurable products: in the variant modes each of their variants becomes a feed item of its own.
     */
    public function countConfigurable(FeedInterface $feed, StoreContext $store): int
    {
        return (int)$this->createFilteredCollection($feed, $store, ['name'])
            ->addAttributeToFilter('type_id', 'configurable')
            ->getSize();
    }

    /**
     * @return array{items: ProductContext[], last_id: int, processed: int}
     * @throws LocalizedException
     */
    public function getBatch(FeedInterface $feed, StoreContext $store, Needs $needs, int $afterId, int $limit): array
    {
        $collection = $this->createFilteredCollection($feed, $store, $needs->attributeCodes);
        $collection->addFieldToFilter('entity_id', ['gt' => $afterId])
            ->setOrder('entity_id', 'ASC')
            ->setPageSize(max(1, $limit))
            ->setCurPage(1);

        $products = $this->loadCollection($collection, $needs);
        if (!$products) {
            return ['items' => [], 'last_id' => $afterId, 'processed' => 0];
        }

        $batch = new BatchData();
        $contexts = $this->createContexts($products, $batch, $store, $needs);
        $ids = array_keys($contexts);
        $lastId = (int)max($ids);

        $mode = $feed->getParentChildMode();
        if ($needs->children) {
            $this->attachChildren($contexts, $batch, $store, $needs, (bool)$feed->getData(FeedInterface::FILTER_IN_STOCK_ONLY));
        }

        $parents = $needs->parents || $mode !== FeedInterface::MODE_PARENT ? $this->dataLoader->getParents($ids) : [];
        if ($needs->parents && $parents) {
            $this->attachParents($contexts, $parents, $batch, $store, $needs);
        }

        $items = [];
        foreach ($contexts as $id => $context) {
            if ($mode === FeedInterface::MODE_PARENT) {
                $items[] = $context;
                continue;
            }
            if ($context->getTypeId() === 'configurable') {
                if ($mode === FeedInterface::MODE_BOTH) {
                    $items[] = $context;
                }
                foreach ($batch->children[$id] ?? [] as $child) {
                    $child->setParent($context);
                    $items[] = $child;
                }
                continue;
            }
            // Variants are exported with their configurable parent.
            if (($parents[$id][0]['type'] ?? '') === 'configurable') {
                continue;
            }
            $items[] = $context;
        }

        return ['items' => $items, 'last_id' => $lastId, 'processed' => count($contexts)];
    }

    /**
     * @param string[] $attributeCodes
     * @throws LocalizedException
     */
    private function createFilteredCollection(FeedInterface $feed, StoreContext $store, array $attributeCodes): Collection
    {
        $collection = $this->createCollection($store, $attributeCodes);

        if ($feed->getData(FeedInterface::FILTER_ENABLED_ONLY) === null || $feed->getData(FeedInterface::FILTER_ENABLED_ONLY)) {
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        }
        $types = $feed->getListValue(FeedInterface::FILTER_TYPES);
        if ($types) {
            $collection->addAttributeToFilter('type_id', ['in' => $types]);
        }
        $sets = array_map('intval', $feed->getListValue(FeedInterface::FILTER_ATTRIBUTE_SETS));
        if ($sets) {
            $collection->addAttributeToFilter('attribute_set_id', ['in' => $sets]);
        }
        $visibility = array_map('intval', $feed->getListValue(FeedInterface::FILTER_VISIBILITY));
        if ($visibility) {
            $collection->addAttributeToFilter('visibility', ['in' => $visibility]);
        }
        if ($feed->getData(FeedInterface::FILTER_IN_STOCK_ONLY)) {
            $this->addInStockFilter($collection);
        }
        $categoryIds = array_map('intval', $feed->getListValue(FeedInterface::FILTER_CATEGORIES));
        if ($categoryIds) {
            $withChildren = $this->withDescendants($categoryIds, $store);
            $mode = (string)$feed->getData(FeedInterface::FILTER_CATEGORY_MODE) === 'exclude' ? 'nin' : 'in';
            $collection->addCategoriesFilter([$mode => $withChildren]);
        }
        $skus = array_values(array_filter(array_map(
            'trim',
            preg_split('/[\r\n,]+/', (string)$feed->getData(FeedInterface::EXCLUDE_SKUS)) ?: []
        )));
        if ($skus) {
            $collection->addAttributeToFilter('sku', ['nin' => $skus]);
        }
        foreach ($feed->getConditions() as $index => $condition) {
            $this->addCondition($collection, $condition, $store->storeId, $index + 1);
        }
        return $collection;
    }

    /**
     * @param string[] $attributeCodes
     */
    private function createCollection(StoreContext $store, array $attributeCodes): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($store->storeId)
            ->addStoreFilter($store->storeId)
            ->addAttributeToSelect($attributeCodes);
        return $collection;
    }

    private function addInStockFilter(Collection $collection): void
    {
        $collection->getSelect()->join(
            ['sw_stock' => $collection->getTable('cataloginventory_stock_status')],
            'sw_stock.product_id = e.entity_id AND sw_stock.website_id = 0 AND sw_stock.stock_id = 1',
            []
        )->where('sw_stock.stock_status = ?', 1);
    }

    /**
     * @param array{attribute: string, operator: string, value: string} $condition
     * @throws LocalizedException
     */
    private function addCondition(Collection $collection, array $condition, int $storeId, int $number): void
    {
        $code = trim($condition['attribute']);
        $operator = $condition['operator'] ?: 'eq';
        $value = trim($condition['value']);
        if ($code === '') {
            return;
        }
        if (!isset(self::OPERATORS[$operator])) {
            throw new LocalizedException(__('Condition %1: unknown operator "%2".', $number, $operator));
        }
        $isStatic = in_array($code, self::STATIC_FIELDS, true);
        $attribute = $isStatic ? null : $this->attributeResolver->getAttribute($code);
        if (!$isStatic && $attribute === null) {
            throw new LocalizedException(__('Condition %1: the attribute "%2" does not exist.', $number, $code));
        }

        if ($operator === 'null') {
            $collection->addAttributeToFilter($code, ['null' => true], 'left');
            return;
        }
        if ($operator === 'notnull') {
            $collection->addAttributeToFilter($code, ['notnull' => true]);
            return;
        }

        $values = in_array($operator, ['in', 'nin'], true)
            ? array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($v) => $v !== ''))
            : [$value];
        $isOptionAttribute = $attribute !== null && $attribute->usesSource()
            && in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'], true);
        if ($isOptionAttribute) {
            foreach ($values as $i => $text) {
                $values[$i] = $this->attributeResolver->getOptionId($code, $text, $storeId) ?? $text;
            }
        }
        $isMultiselect = $attribute !== null && $attribute->getFrontendInput() === 'multiselect';

        switch ($operator) {
            case 'in':
            case 'nin':
                if ($isMultiselect) {
                    $or = array_map(static fn ($v) => ['finset' => $v], $values);
                    if ($operator === 'in') {
                        $collection->addAttributeToFilter($code, $or ?: [['eq' => '__none__']]);
                    } else {
                        foreach ($values as $v) {
                            $collection->addAttributeToFilter($code, [['nfinset' => $v], ['null' => true]], 'left');
                        }
                    }
                    return;
                }
                $values = $values ?: ['__none__'];
                if ($operator === 'nin' && !$isStatic) {
                    $collection->addAttributeToFilter($code, [['nin' => $values], ['null' => true]], 'left');
                } else {
                    $collection->addAttributeToFilter($code, [$operator => $values]);
                }
                return;
            case 'like':
            case 'nlike':
                $pattern = str_contains($value, '%') ? $value : '%' . $value . '%';
                if ($operator === 'nlike' && !$isStatic) {
                    $collection->addAttributeToFilter($code, [['nlike' => $pattern], ['null' => true]], 'left');
                } else {
                    $collection->addAttributeToFilter($code, [$operator => $pattern]);
                }
                return;
            case 'neq':
                if ($isMultiselect) {
                    $collection->addAttributeToFilter($code, [['nfinset' => $values[0]], ['null' => true]], 'left');
                } elseif ($isStatic) {
                    $collection->addAttributeToFilter($code, ['neq' => $values[0]]);
                } else {
                    $collection->addAttributeToFilter($code, [['neq' => $values[0]], ['null' => true]], 'left');
                }
                return;
            case 'eq':
                if ($isMultiselect) {
                    $collection->addAttributeToFilter($code, ['finset' => $values[0]]);
                } else {
                    $collection->addAttributeToFilter($code, ['eq' => $values[0]]);
                }
                return;
            default:
                if (!is_numeric($values[0]) && !preg_match('/^\d{4}-\d{2}-\d{2}/', $values[0])) {
                    throw new LocalizedException(
                        __('Condition %1: "%2" needs a number or a date (YYYY-MM-DD).', $number, self::OPERATORS[$operator])
                    );
                }
                $collection->addAttributeToFilter($code, [$operator => $values[0]]);
        }
    }

    /**
     * @param int[] $categoryIds
     * @return int[]
     */
    private function withDescendants(array $categoryIds, StoreContext $store): array
    {
        $selected = array_flip($categoryIds);
        $result = $categoryIds;
        foreach ($store->categories as $id => $category) {
            foreach ($category['path_ids'] as $pathId) {
                if (isset($selected[$pathId])) {
                    $result[] = $id;
                    break;
                }
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * @return Product[]
     */
    private function loadCollection(Collection $collection, Needs $needs): array
    {
        $collection->addUrlRewrite();
        $collection->load();
        if ($needs->gallery && $collection->count()) {
            $collection->addMediaGalleryData();
        }
        $products = [];
        foreach ($collection as $product) {
            $products[(int)$product->getId()] = $product;
        }
        return $products;
    }

    /**
     * @param Product[] $products
     * @return array<int, ProductContext>
     */
    private function createContexts(array $products, BatchData $batch, StoreContext $store, Needs $needs): array
    {
        $this->preload($products, $batch, $store, $needs);
        $contexts = [];
        foreach ($products as $id => $product) {
            $contexts[$id] = new ProductContext($product, $batch, $store, $this->attributeResolver, $this->priceCalculator);
        }
        return $contexts;
    }

    /**
     * @param Product[] $products
     */
    private function preload(array $products, BatchData $batch, StoreContext $store, Needs $needs): void
    {
        $ids = array_keys($products);
        $batch->stock += $this->dataLoader->getStock($ids);
        $batch->prices += $this->dataLoader->getIndexPrices($ids, $store->websiteId);
        if ($needs->categories) {
            $batch->categoryIds += $this->dataLoader->getCategoryIds($ids);
        }
        if ($needs->reviews) {
            $batch->reviews += $this->dataLoader->getReviewSummaries($ids, $store->storeId);
        }
        if ($needs->tierPrices) {
            $batch->tierPrices += $this->dataLoader->getTierPrices($this->linkIds($products), $store->websiteId);
        }
    }

    /**
     * @param array<int, ProductContext> $contexts
     */
    private function attachChildren(
        array $contexts,
        BatchData $batch,
        StoreContext $store,
        Needs $needs,
        bool $inStockOnly
    ): void {
        $composites = [];
        foreach ($contexts as $id => $context) {
            if (in_array($context->getTypeId(), ['configurable', 'bundle', 'grouped'], true)) {
                $composites[$id] = $context->getProduct();
            }
        }
        if (!$composites) {
            return;
        }
        $childIds = $this->dataLoader->getChildIds($this->linkIds($composites));
        $allChildIds = array_values(array_unique(array_merge([], ...array_values($childIds))));
        if (!$allChildIds) {
            return;
        }
        $collection = $this->createCollection($store, $needs->attributeCodes);
        $collection->addAttributeToFilter('entity_id', ['in' => $allChildIds])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED);
        if ($inStockOnly) {
            $this->addInStockFilter($collection);
        }
        $children = $this->createContexts($this->loadCollection($collection, $needs), $batch, $store, $needs);
        foreach ($childIds as $parentId => $ids) {
            foreach ($ids as $childId) {
                if (isset($children[$childId])) {
                    $child = $children[$childId];
                    if ($child->getParent() === null) {
                        $child->setParent($contexts[$parentId]);
                        $batch->children[$parentId][] = $child;
                    } else {
                        // A product can belong to several parents: give each parent its own context.
                        $copy = new ProductContext(
                            $child->getProduct(),
                            $batch,
                            $store,
                            $this->attributeResolver,
                            $this->priceCalculator,
                            $contexts[$parentId]
                        );
                        $batch->children[$parentId][] = $copy;
                    }
                }
            }
        }
    }

    /**
     * @param array<int, ProductContext> $contexts
     * @param array<int, array<int, array{id: int, type: string}>> $parents
     */
    private function attachParents(array $contexts, array $parents, BatchData $batch, StoreContext $store, Needs $needs): void
    {
        $parentIds = [];
        foreach ($parents as $list) {
            $parentIds[] = $list[0]['id'];
        }
        $parentIds = array_values(array_unique($parentIds));
        $known = array_intersect_key($contexts, array_flip($parentIds));
        $missing = array_diff($parentIds, array_keys($known));
        $loaded = [];
        if ($missing) {
            $collection = $this->createCollection($store, $needs->attributeCodes);
            $collection->addAttributeToFilter('entity_id', ['in' => $missing]);
            $loaded = $this->createContexts($this->loadCollection($collection, $needs), $batch, $store, $needs);
        }
        $all = $known + $loaded;
        foreach ($parents as $childId => $list) {
            $parentId = $list[0]['id'];
            if (isset($contexts[$childId], $all[$parentId]) && $contexts[$childId]->getParent() === null) {
                $contexts[$childId]->setParent($all[$parentId]);
            }
        }
    }

    /**
     * @param Product[] $products
     * @return array<int, int> entity ID => link ID
     */
    private function linkIds(array $products): array
    {
        $linkField = $this->dataLoader->getLinkField();
        $result = [];
        foreach ($products as $id => $product) {
            $result[(int)$id] = (int)($product->getData($linkField) ?: $id);
        }
        return $result;
    }
}
