<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\ResourceModel\Feed;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Softaware\DataFeed\Model\Feed;
use Softaware\DataFeed\Model\ResourceModel\Feed as FeedResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'feed_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'softaware_datafeed_feed_collection';

    protected function _construct(): void
    {
        $this->_init(Feed::class, FeedResource::class);
    }
}
