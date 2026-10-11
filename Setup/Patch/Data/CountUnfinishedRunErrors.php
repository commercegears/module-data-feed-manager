<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Before 1.5.4 a run that never finished was marked as failed with 0 errors. Counts it as one error, so the log's
 * Errors column agrees with the status.
 */
class CountUnfinishedRunErrors implements DataPatchInterface
{
    public function __construct(private readonly ModuleDataSetupInterface $setup)
    {
    }

    public function apply(): self
    {
        $this->setup->getConnection()->update(
            $this->setup->getTable('softaware_datafeed_log'),
            ['error_count' => 1],
            ['status = ?' => 'error', 'error_count = ?' => 0]
        );
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
