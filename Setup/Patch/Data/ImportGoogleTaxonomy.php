<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;
use CommerceGears\DataFeed\Model\ResourceModel\Taxonomy;
use CommerceGears\DataFeed\Model\Taxonomy\Importer;

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
                $this->logger->warning('CommerceGears_DataFeed: Google taxonomy not imported: ' . $e->getMessage());
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
