<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;
use Softaware\DataFeed\Model\ResourceModel\Taxonomy;
use Softaware\DataFeed\Model\Taxonomy\Importer;

/**
 * Loads the bundled Google product taxonomy (en-GB) so category mapping works straight after installation.
 */
class ImportGoogleTaxonomy implements DataPatchInterface
{
    public function __construct(
        private readonly Importer $importer,
        private readonly Taxonomy $taxonomy,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        if ($this->taxonomy->count() === 0) {
            try {
                $this->importer->importFile();
            } catch (\Throwable $e) {
                $this->logger->warning('Softaware_DataFeed: Google taxonomy not imported: ' . $e->getMessage());
            }
        }
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
