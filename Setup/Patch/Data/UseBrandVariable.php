<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Feeds created from the shipped presets before 1.4.0 read the brand from "manufacturer" only. Replaces exactly
 * those shipped expressions with {{ product.brand }} (Brand Attribute setting); edited templates are left alone.
 */
class UseBrandVariable implements DataPatchInterface
{
    private const OLD_TEMPLATE = '{{ product.manufacturer ?? product.parent.manufacturer ?? store.name }}';
    private const NEW_TEMPLATE = '{{ product.brand ?? store.name }}';
    private const OLD_COLUMN = '{{ product.manufacturer ?? product.parent.manufacturer }}';
    private const NEW_COLUMN = '{{ product.brand }}';

    public function __construct(private readonly ModuleDataSetupInterface $setup)
    {
    }

    public function apply(): self
    {
        $connection = $this->setup->getConnection();
        $table = $this->setup->getTable('softaware_datafeed_feed');
        $rows = $connection->fetchAll(
            $connection->select()->from($table, ['feed_id', 'template', 'columns'])
        );
        foreach ($rows as $row) {
            $update = [];
            $template = (string)$row['template'];
            if (str_contains($template, self::OLD_TEMPLATE)) {
                $update['template'] = str_replace(self::OLD_TEMPLATE, self::NEW_TEMPLATE, $template);
            }
            $columns = json_decode((string)$row['columns'], true);
            if (is_array($columns)) {
                $changed = false;
                foreach ($columns as $i => $column) {
                    if (is_array($column) && ($column['value'] ?? null) === self::OLD_COLUMN) {
                        $columns[$i]['value'] = self::NEW_COLUMN;
                        $changed = true;
                    }
                }
                if ($changed) {
                    $update['columns'] = json_encode(array_values($columns), JSON_UNESCAPED_UNICODE);
                }
            }
            if ($update) {
                $connection->update($table, $update, ['feed_id = ?' => (int)$row['feed_id']]);
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
