<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Softaware\DataFeed\Api\FeedRepositoryInterface;
use Softaware\DataFeed\Model\Generator\Preview;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PreviewCommand extends Command
{
    public function __construct(
        private readonly Preview $preview,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('softaware:feed:preview')
            ->setDescription('Print the first items of a feed without writing the file')
            ->addArgument('id', InputArgument::REQUIRED, 'Feed ID')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Number of items', '3');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Exception) {
            // Area already set.
        }
        try {
            $feed = $this->feedRepository->getById((int)$input->getArgument('id'));
            $result = $this->preview->render($feed, max(1, (int)$input->getOption('limit')));
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->write($result['output'], false, OutputInterface::OUTPUT_RAW);
        $output->writeln('');
        $output->writeln(sprintf('<info>%d items shown, %d products match the filters.</info>', $result['items'], $result['total']));
        foreach ($result['errors'] as $error) {
            $output->writeln('<comment>' . $error . '</comment>');
        }
        if (!$result['valid']) {
            $output->writeln('<error>' . $result['validation'] . '</error>');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
