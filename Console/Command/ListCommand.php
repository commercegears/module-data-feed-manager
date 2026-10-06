<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Console\Command;

use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Model\Generator\FileLocator;
use Softaware\DataFeed\Model\ResourceModel\Feed\CollectionFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly FileLocator $fileLocator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('softaware:feed:list')->setDescription('List product feeds');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);
        $table->setHeaders(['ID', 'Name', 'Store', 'Format', 'Enabled', 'Schedule', 'Last run (UTC)', 'Items', 'Status', 'URL']);
        /** @var FeedInterface $feed */
        foreach ($this->collectionFactory->create()->setOrder('feed_id', 'ASC') as $feed) {
            $schedule = match ($feed->getScheduleType()) {
                FeedInterface::SCHEDULE_TIMES_DAILY => 'daily ' . $feed->getData(FeedInterface::SCHEDULE_TIMES),
                FeedInterface::SCHEDULE_CRON_EXPR => 'cron ' . $feed->getData(FeedInterface::SCHEDULE_CRON),
                default => 'manual',
            };
            $table->addRow([
                $feed->getId(),
                $feed->getName(),
                $feed->getStoreId(),
                strtoupper($feed->getFormat()),
                $feed->isActive() ? 'yes' : 'no',
                $schedule,
                $feed->getData(FeedInterface::GENERATED_AT) ?: '-',
                $feed->getData(FeedInterface::GENERATED_ITEMS) ?? '-',
                $feed->getData(FeedInterface::GENERATED_STATUS) ?: '-',
                $this->fileLocator->getPublicUrl($feed) ?? $this->fileLocator->getAbsolutePath($feed),
            ]);
        }
        $table->render();
        return Command::SUCCESS;
    }
}
