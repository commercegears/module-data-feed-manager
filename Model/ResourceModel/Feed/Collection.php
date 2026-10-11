<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\ResourceModel\Feed;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use CommerceGears\DataFeed\Model\Feed;
use CommerceGears\DataFeed\Model\ResourceModel\Feed as FeedResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'feed_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'commercegears_datafeed_feed_collection';

    protected function _construct(): void
    {
        $this->_init(Feed::class, FeedResource::class);
    }
}
