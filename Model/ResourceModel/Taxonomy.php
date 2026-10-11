<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Google product taxonomy storage and search.
 */
class Taxonomy
{
    public const TABLE = 'commercegears_datafeed_taxonomy';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * Replaces the stored taxonomy.
     *
     * @param array<int, string> $paths taxonomy ID => full path ("Apparel & Accessories > Clothing")
     */
    public function replaceAll(array $paths): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $connection->beginTransaction();
        try {
            $connection->delete($table);
            $rows = [];
            foreach ($paths as $id => $path) {
                $parts = explode(' > ', $path);
                $rows[] = ['taxonomy_id' => (int)$id, 'name' => mb_substr((string)end($parts), 0, 255), 'path' => $path];
                if (count($rows) === 1000) {
                    $connection->insertMultiple($table, $rows);
                    $rows = [];
                }
            }
            if ($rows) {
                $connection->insertMultiple($table, $rows);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        return count($paths);
    }

    public function count(): int
    {
        $connection = $this->resource->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()->from($this->resource->getTableName(self::TABLE), ['COUNT(*)'])
        );
    }

    /**
     * Searches by ID or by words in the path (all words must match).
     *
     * @return array<int, string> taxonomy ID => path
     */
    public function search(string $term, int $limit = 20): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['taxonomy_id', 'path'])
            ->limit($limit);
        $term = trim($term);
        if (ctype_digit($term)) {
            $select->where('taxonomy_id = ?', (int)$term);
        } else {
            foreach (preg_split('/\s+/', $term) ?: [] as $word) {
                if ($word === '' || $word === '>') {
                    continue;
                }
                $select->where('path LIKE ?', '%' . addcslashes($word, '%_\\') . '%');
            }
            // Prefer short paths and names that start with the term.
            $select->order(new \Magento\Framework\DB\Sql\Expression($connection->quoteInto('name LIKE ? DESC', addcslashes($term, '%_\\') . '%')));
            $select->order(new \Magento\Framework\DB\Sql\Expression('CHAR_LENGTH(path) ASC'));
        }
        return array_map('strval', $connection->fetchPairs($select));
    }

    /**
     * @param int[] $ids
     * @return array<int, string>
     */
    public function getPaths(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['taxonomy_id', 'path'])
            ->where('taxonomy_id IN (?)', array_map('intval', $ids));
        return array_map('strval', $connection->fetchPairs($select));
    }
}
