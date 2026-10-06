<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\ResourceModel\Log;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Softaware\DataFeed\Model\Log;
use Softaware\DataFeed\Model\ResourceModel\Log as LogResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'log_id';

    protected function _construct(): void
    {
        $this->_init(Log::class, LogResource::class);
    }
}
