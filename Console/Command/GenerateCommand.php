<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use CommerceGears\DataFeed\Api\FeedGeneratorInterface;
use CommerceGears\DataFeed\Api\FeedRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    public function __construct(
        private readonly FeedGeneratorInterface $generator,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('commercegears:feed:generate')
            ->setDescription('Generate one or more product feeds')
            ->addArgument('id', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Feed ID(s)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Generate all enabled feeds');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_CRONTAB);
        } catch (\Exception) {
            // Area already set.
        }
        $ids = array_map('intval', (array)$input->getArgument('id'));
        if ($input->getOption('all')) {
            $ids = array_map(static fn ($feed) => (int)$feed->getId(), $this->feedRepository->getActiveFeeds());
        }
        if (!$ids) {
            $output->writeln('<error>Give a feed ID or use --all. List feeds with commercegears:feed:list.</error>');
            return Command::INVALID;
        }

        $exitCode = Command::SUCCESS;
        foreach ($ids as $id) {
            try {
                $feed = $this->feedRepository->getById($id);
                $output->writeln(sprintf('<info>Feed %d: %s</info>', $id, $feed->getName()));
                $bar = new ProgressBar($output);
                $bar->setFormat(' %current%/%max% products [%bar%] %elapsed%');
                $result = $this->generator->generate(
                    $id,
                    FeedGeneratorInterface::TRIGGER_CLI,
                    static function (int $processed, int $total) use ($bar): void {
                        $bar->setMaxSteps(max($total, $processed));
                        $bar->setProgress($processed);
                    }
                );
                $bar->finish();
                $output->writeln('');
                $output->writeln(sprintf(
                    '  %s: %d items, %d errors, %s bytes, %.1fs',
                    strtoupper($result->status),
                    $result->items,
                    $result->errors,
                    number_format($result->fileSize),
                    $result->duration
                ));
                $output->writeln('  File: ' . $result->filePath);
                if ($result->url) {
                    $output->writeln('  URL:  ' . $result->url);
                }
                foreach (array_slice($result->messages, 0, 20) as $message) {
                    $output->writeln('  - ' . $message);
                }
                if ($result->status === 'error') {
                    $exitCode = Command::FAILURE;
                }
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>Feed %d: %s</error>', $id, $e->getMessage()));
                $exitCode = Command::FAILURE;
            }
        }
        return $exitCode;
    }
}
