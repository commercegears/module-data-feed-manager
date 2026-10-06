<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Taxonomy;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Softaware\DataFeed\Model\ResourceModel\Taxonomy;

/**
 * Imports the Google product taxonomy file "taxonomy-with-ids.<locale>.txt"
 * (lines like "1604 - Apparel & Accessories > Clothing").
 * Download other languages from https://www.google.com/basepages/producttype/taxonomy-with-ids.<locale>.txt
 */
class Importer
{
    public const BUNDLED_FILE = 'files/taxonomy/taxonomy-with-ids.en-GB.txt';

    public function __construct(
        private readonly Taxonomy $taxonomy,
        private readonly ModuleDirReader $moduleDirReader
    ) {
    }

    public function getBundledFile(): string
    {
        return $this->moduleDirReader->getModuleDir('', 'Softaware_DataFeed') . '/' . self::BUNDLED_FILE;
    }

    /**
     * @return int number of categories imported
     * @throws LocalizedException
     */
    public function importFile(?string $path = null): int
    {
        $path = $path ?: $this->getBundledFile();
        if (!is_file($path) || !is_readable($path)) {
            throw new LocalizedException(__('The taxonomy file %1 cannot be read.', $path));
        }
        if (filesize($path) > 10 * 1024 * 1024) {
            throw new LocalizedException(__('The taxonomy file is larger than 10 MB.'));
        }
        return $this->importContent((string)file_get_contents($path));
    }

    /**
     * @throws LocalizedException
     */
    public function importContent(string $content): int
    {
        $content = (string)preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new LocalizedException(__('The taxonomy file must be UTF-8 encoded.'));
        }
        $paths = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('/^(\d+)\s+-\s+(.+)$/u', $line, $m)) {
                continue;
            }
            $paths[(int)$m[1]] = mb_substr(trim($m[2]), 0, 1024);
        }
        if (count($paths) < 10) {
            throw new LocalizedException(
                __('No categories found. Use the file with IDs ("taxonomy-with-ids"), lines look like "1604 - Apparel & Accessories > Clothing".')
            );
        }
        return $this->taxonomy->replaceAll($paths);
    }
}
