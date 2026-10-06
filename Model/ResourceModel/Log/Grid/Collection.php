<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\ResourceModel\Log\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Generation log grid: log rows with the feed name.
 */
class Collection extends SearchResult
{
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->joinLeft(
            ['feed' => $this->getTable('softaware_datafeed_feed')],
            'feed.feed_id = main_table.feed_id',
            ['feed_name' => 'feed.name']
        );
        $this->addFilterToMap('feed_id', 'main_table.feed_id');
        $this->addFilterToMap('feed_name', 'feed.name');
        $this->addFilterToMap('log_id', 'main_table.log_id');
        $this->addFilterToMap('status', 'main_table.status');
        return $this;
    }
}
