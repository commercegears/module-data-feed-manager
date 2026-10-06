<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Console\Command;

use Softaware\DataFeed\Model\Taxonomy\Importer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TaxonomyImportCommand extends Command
{
    public function __construct(private readonly Importer $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('softaware:feed:taxonomy-import')
            ->setDescription('Import the Google product taxonomy (taxonomy-with-ids.<locale>.txt); without a file the bundled en-GB list is used')
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to a taxonomy-with-ids file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $count = $this->importer->importFile($input->getArgument('file') ?: null);
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln(sprintf('<info>%d Google product categories imported.</info>', $count));
        return Command::SUCCESS;
    }
}
