<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Api\FeedRepositoryInterface;
use CommerceGears\DataFeed\Model\ResourceModel\Feed as FeedResource;
use CommerceGears\DataFeed\Model\ResourceModel\Feed\CollectionFactory;

class FeedRepository implements FeedRepositoryInterface
{
    public function __construct(
        private readonly FeedFactory $feedFactory,
        private readonly FeedResource $feedResource,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function getById(int $feedId): FeedInterface
    {
        $feed = $this->feedFactory->create();
        $this->feedResource->load($feed, $feedId);
        if (!$feed->getId()) {
            throw new NoSuchEntityException(__('The feed with ID "%1" does not exist.', $feedId));
        }
        return $feed;
    }

    public function save(FeedInterface $feed): FeedInterface
    {
        try {
            /** @var Feed $feed */
            $this->feedResource->save($feed);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the feed: %1', $e->getMessage()), $e);
        }
        return $feed;
    }

    public function delete(FeedInterface $feed): bool
    {
        try {
            /** @var Feed $feed */
            $this->feedResource->delete($feed);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the feed: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $feedId): bool
    {
        return $this->delete($this->getById($feedId));
    }

    public function getActiveFeeds(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(FeedInterface::IS_ACTIVE, 1);
        return array_values($collection->getItems());
    }
}
