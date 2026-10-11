<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Api;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use CommerceGears\DataFeed\Api\Data\FeedInterface;

interface FeedRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $feedId): FeedInterface;

    /**
     * @throws CouldNotSaveException
     */
    public function save(FeedInterface $feed): FeedInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(FeedInterface $feed): bool;

    /**
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $feedId): bool;

    /**
     * @return FeedInterface[]
     */
    public function getActiveFeeds(): array;
}
