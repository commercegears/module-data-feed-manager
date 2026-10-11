<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\ResourceModel\Log;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use CommerceGears\DataFeed\Model\Log;
use CommerceGears\DataFeed\Model\ResourceModel\Log as LogResource;

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
