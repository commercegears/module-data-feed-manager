<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Meta feeds created from the shipped preset before 1.5.4 sent quantity_to_sell_on_facebook = 0 for in-stock
 * products without a stock quantity (bundles, grouped, downloadable, unmanaged stock). Replaces exactly the shipped
 * expression of that column with one that leaves it empty then; edited columns are left alone.
 */
class MetaQuantityOnlyWhenKnown implements DataPatchInterface
{
    private const HEADER = 'quantity_to_sell_on_facebook';
    private const OLD_VALUE = '{{ product.qty | round }}';
    private const NEW_VALUE = '{% if product.qty > 0 %}{{ product.qty | round }}{% endif %}';

    public function __construct(private readonly ModuleDataSetupInterface $setup)
    {
    }

    public function apply(): self
    {
        $connection = $this->setup->getConnection();
        $table = $this->setup->getTable('softaware_datafeed_feed');
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['feed_id', 'columns'])
                ->where('columns LIKE ?', '%' . self::HEADER . '%')
        );
        foreach ($rows as $feedId => $json) {
            $columns = json_decode((string)$json, true);
            if (!is_array($columns)) {
                continue;
            }
            $changed = false;
            foreach ($columns as $i => $column) {
                if (is_array($column) && ($column['header'] ?? null) === self::HEADER
                    && ($column['value'] ?? null) === self::OLD_VALUE
                ) {
                    $columns[$i]['value'] = self::NEW_VALUE;
                    $changed = true;
                }
            }
            if ($changed) {
                $connection->update(
                    $table,
                    ['columns' => json_encode(array_values($columns), JSON_UNESCAPED_UNICODE)],
                    ['feed_id = ?' => (int)$feedId]
                );
            }
        }
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
