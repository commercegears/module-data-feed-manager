<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Cron;

use Psr\Log\LoggerInterface;
use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Api\FeedGeneratorInterface;
use CommerceGears\DataFeed\Model\Config;
use CommerceGears\DataFeed\Model\Generator\JobStorage;
use CommerceGears\DataFeed\Model\ResourceModel\Feed as FeedResource;
use CommerceGears\DataFeed\Model\ResourceModel\Feed\CollectionFactory;
use CommerceGears\DataFeed\Model\Schedule\NextRunCalculator;

/**
 * Runs every minute (own cron group) and generates the feeds that are due.
 */
class GenerateScheduled
{
    public function __construct(
        private readonly Config $config,
        private readonly CollectionFactory $collectionFactory,
        private readonly FeedResource $feedResource,
        private readonly FeedGeneratorInterface $generator,
        private readonly NextRunCalculator $nextRunCalculator,
        private readonly JobStorage $jobStorage,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(FeedInterface::IS_ACTIVE, 1)
            ->addFieldToFilter(FeedInterface::SCHEDULE_TYPE, ['neq' => FeedInterface::SCHEDULE_MANUAL])
            ->addFieldToFilter(FeedInterface::NEXT_RUN_AT, ['notnull' => true])
            ->addFieldToFilter(FeedInterface::NEXT_RUN_AT, ['lteq' => $now])
            ->setOrder(FeedInterface::NEXT_RUN_AT, 'ASC');

        /** @var FeedInterface $feed */
        foreach ($collection as $feed) {
            $feedId = (int)$feed->getId();
            if ($this->jobStorage->isLocked($feedId)) {
                continue;
            }
            // Move the schedule on first, so a failing feed is not retried every minute.
            try {
                $next = $this->nextRunCalculator->calculate($feed);
            } catch (\Throwable $e) {
                $next = null;
                $this->logger->error(sprintf('CommerceGears_DataFeed: feed %d has an invalid schedule: %s', $feedId, $e->getMessage()));
            }
            $this->feedResource->updateFields($feedId, [FeedInterface::NEXT_RUN_AT => $next]);
            try {
                $this->generator->generate($feedId, FeedGeneratorInterface::TRIGGER_CRON);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('CommerceGears_DataFeed: feed %d failed: %s', $feedId, $e->getMessage()));
            }
        }
    }
}
