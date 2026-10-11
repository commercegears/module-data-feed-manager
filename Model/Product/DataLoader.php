<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Loads per-product data for a whole batch with one query per data type.
 */
class DataLoader
{
    private ?string $linkField = null;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly MetadataPool $metadataPool
    ) {
    }

    public function getLinkField(): string
    {
        if ($this->linkField === null) {
            $this->linkField = (string)$this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        }
        return $this->linkField;
    }

    /**
     * @param int[] $ids
     * @return array<int, array{qty: float, is_in_stock: int, stock_status: int}>
     */
    public function getStock(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $connection = $this->connection();
        $select = $connection->select()
            ->from(['si' => $this->resource->getTableName('cataloginventory_stock_item')], ['product_id', 'qty', 'is_in_stock'])
            ->joinLeft(
                ['ss' => $this->resource->getTableName('cataloginventory_stock_status')],
                'ss.product_id = si.product_id AND ss.website_id = 0 AND ss.stock_id = si.stock_id',
                ['stock_status']
            )
            ->where('si.product_id IN (?)', $ids)
            ->where('si.stock_id = ?', 1);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $isInStock = (int)$row['is_in_stock'];
            $result[(int)$row['product_id']] = [
                'qty' => (float)$row['qty'],
                'is_in_stock' => $isInStock,
                'stock_status' => $row['stock_status'] === null ? $isInStock : (int)$row['stock_status'],
            ];
        }
        return $result;
    }

    /**
     * Prices from the price index for guests (customer group 0).
     *
     * @param int[] $ids
     * @return array<int, array{price: ?float, final_price: ?float, min_price: ?float, max_price: ?float}>
     */
    public function getIndexPrices(array $ids, int $websiteId, int $customerGroupId = 0): array
    {
        if (!$ids) {
            return [];
        }
        $connection = $this->connection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('catalog_product_index_price'),
                ['entity_id', 'price', 'final_price', 'min_price', 'max_price']
            )
            ->where('entity_id IN (?)', $ids)
            ->where('website_id = ?', $websiteId)
            ->where('customer_group_id = ?', $customerGroupId);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['entity_id']] = [
                'price' => $row['price'] === null ? null : (float)$row['price'],
                'final_price' => $row['final_price'] === null ? null : (float)$row['final_price'],
                'min_price' => $row['min_price'] === null ? null : (float)$row['min_price'],
                'max_price' => $row['max_price'] === null ? null : (float)$row['max_price'],
            ];
        }
        return $result;
    }

    /**
     * @param array<int, int> $linkIds entity ID => link ID (row_id on Adobe Commerce, entity_id otherwise)
     * @return array<int, array<int, array{qty: float, value: ?float, percentage: ?float, all_groups: int, customer_group_id: int}>>
     */
    public function getTierPrices(array $linkIds, int $websiteId): array
    {
        if (!$linkIds) {
            return [];
        }
        $linkField = $this->getLinkField();
        $connection = $this->connection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('catalog_product_entity_tier_price'),
                [$linkField, 'qty', 'value', 'percentage_value', 'all_groups', 'customer_group_id']
            )
            ->where($linkField . ' IN (?)', array_values($linkIds))
            ->where('website_id IN (?)', [0, $websiteId])
            ->order('qty ASC');
        $byLink = array_flip($linkIds);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $entityId = $byLink[(int)$row[$linkField]] ?? null;
            if ($entityId === null) {
                continue;
            }
            $result[$entityId][] = [
                'qty' => (float)$row['qty'],
                'value' => $row['value'] === null ? null : (float)$row['value'],
                'percentage' => $row['percentage_value'] === null ? null : (float)$row['percentage_value'],
                'all_groups' => (int)$row['all_groups'],
                'customer_group_id' => (int)$row['customer_group_id'],
            ];
        }
        return $result;
    }

    /**
     * @param int[] $ids
     * @return array<int, int[]> product ID => category IDs
     */
    public function getCategoryIds(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_category_product'), ['product_id', 'category_id'])
            ->where('product_id IN (?)', $ids)
            ->order('position ASC');
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['product_id']][] = (int)$row['category_id'];
        }
        return $result;
    }

    /**
     * @param int[] $ids
     * @return array<int, array{count: int, summary: int}>
     */
    public function getReviewSummaries(array $ids, int $storeId): array
    {
        if (!$ids) {
            return [];
        }
        $connection = $this->connection();
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('review_entity_summary'),
                ['entity_pk_value', 'reviews_count', 'rating_summary']
            )
            ->where('entity_pk_value IN (?)', $ids)
            ->where('entity_type = ?', 1)
            ->where('store_id = ?', $storeId);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['entity_pk_value']] = [
                'count' => (int)$row['reviews_count'],
                'summary' => (int)$row['rating_summary'],
            ];
        }
        return $result;
    }

    /**
     * Children of composite products (configurable, bundle, grouped).
     *
     * @param array<int, int> $parentLinkIds parent entity ID => link ID
     * @return array<int, int[]> parent entity ID => child entity IDs
     */
    public function getChildIds(array $parentLinkIds): array
    {
        if (!$parentLinkIds) {
            return [];
        }
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_product_relation'), ['parent_id', 'child_id'])
            ->where('parent_id IN (?)', array_values($parentLinkIds))
            ->order('child_id ASC');
        $byLink = array_flip($parentLinkIds);
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $parentId = $byLink[(int)$row['parent_id']] ?? null;
            if ($parentId !== null) {
                $result[$parentId][] = (int)$row['child_id'];
            }
        }
        return $result;
    }

    /**
     * Parents of the given products, configurable parents first.
     *
     * @param int[] $childIds
     * @return array<int, array<int, array{id: int, type: string}>> child ID => parents
     */
    public function getParents(array $childIds): array
    {
        if (!$childIds) {
            return [];
        }
        $linkField = $this->getLinkField();
        $connection = $this->connection();
        $select = $connection->select()
            ->from(['r' => $this->resource->getTableName('catalog_product_relation')], ['child_id'])
            ->join(
                ['e' => $this->resource->getTableName('catalog_product_entity')],
                'e.' . $linkField . ' = r.parent_id',
                ['entity_id', 'type_id']
            )
            ->where('r.child_id IN (?)', $childIds)
            ->order(new \Magento\Framework\DB\Sql\Expression("e.type_id = 'configurable' DESC"))
            ->order('e.entity_id ASC');
        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['child_id']][] = ['id' => (int)$row['entity_id'], 'type' => (string)$row['type_id']];
        }
        return $result;
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
}
