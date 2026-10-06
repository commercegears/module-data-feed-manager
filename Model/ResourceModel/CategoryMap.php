<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Store category to Google category mapping (global, shared by all feeds).
 */
class CategoryMap
{
    public const TABLE = 'softaware_datafeed_category_map';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @return array<int, int> category ID => taxonomy ID
     */
    public function getMap(): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from($this->resource->getTableName(self::TABLE), ['category_id', 'taxonomy_id']);
        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * Saves the given categories: a taxonomy ID of 0/null removes the mapping.
     *
     * @param array<int, int|null> $map category ID => taxonomy ID
     */
    public function saveMap(array $map): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $delete = [];
        $rows = [];
        foreach ($map as $categoryId => $taxonomyId) {
            if ((int)$taxonomyId > 0) {
                $rows[] = ['category_id' => (int)$categoryId, 'taxonomy_id' => (int)$taxonomyId];
            } else {
                $delete[] = (int)$categoryId;
            }
        }
        $connection->beginTransaction();
        try {
            if ($delete) {
                $connection->delete($table, ['category_id IN (?)' => $delete]);
            }
            if ($rows) {
                $connection->insertOnDuplicate($table, $rows, ['taxonomy_id']);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
