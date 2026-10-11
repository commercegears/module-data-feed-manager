<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use CommerceGears\DataFeed\Model\ResourceModel\Feed\CollectionFactory;

class Feed implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function toOptionArray(): array
    {
        $result = [];
        foreach ($this->collectionFactory->create()->setOrder('name', 'ASC') as $feed) {
            $result[] = ['value' => (string)$feed->getId(), 'label' => (string)$feed->getData('name')];
        }
        return $result;
    }
}
