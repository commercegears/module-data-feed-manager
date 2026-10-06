<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Cron;

use Psr\Log\LoggerInterface;
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Api\FeedGeneratorInterface;
use Softaware\DataFeed\Model\Config;
use Softaware\DataFeed\Model\Generator\JobStorage;
use Softaware\DataFeed\Model\ResourceModel\Feed as FeedResource;
use Softaware\DataFeed\Model\ResourceModel\Feed\CollectionFactory;
use Softaware\DataFeed\Model\Schedule\NextRunCalculator;

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
                $this->logger->error(sprintf('Softaware_DataFeed: feed %d has an invalid schedule: %s', $feedId, $e->getMessage()));
            }
            $this->feedResource->updateFields($feedId, [FeedInterface::NEXT_RUN_AT => $next]);
            try {
                $this->generator->generate($feedId, FeedGeneratorInterface::TRIGGER_CRON);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('Softaware_DataFeed: feed %d failed: %s', $feedId, $e->getMessage()));
            }
        }
    }
}
