<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Model\Generator\FileLocator;
use Softaware\DataFeed\Model\Generator\RendererProvider;
use Softaware\DataFeed\Model\Product\ProductProvider;
use Softaware\DataFeed\Model\ResourceModel\Feed\CollectionFactory;
use Softaware\DataFeed\Model\Schedule\NextRunCalculator;

/**
 * Checks a feed before it is saved. Returns all problems at once.
 */
class FeedValidator
{
    public function __construct(
        private readonly FileLocator $fileLocator,
        private readonly RendererProvider $rendererProvider,
        private readonly NextRunCalculator $nextRunCalculator,
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return string[] error messages; empty when valid
     */
    public function validate(FeedInterface $feed): array
    {
        $errors = [];
        if (trim($feed->getName()) === '') {
            $errors[] = (string)__('Enter a name.');
        }
        try {
            $this->storeManager->getStore($feed->getStoreId());
            if ($feed->getStoreId() === 0) {
                $errors[] = (string)__('Choose a store view (not "All Store Views").');
            }
        } catch (\Exception) {
            $errors[] = (string)__('Choose a store view.');
        }
        if (!in_array($feed->getFormat(), [FeedInterface::FORMAT_XML, FeedInterface::FORMAT_JSON, FeedInterface::FORMAT_CSV, FeedInterface::FORMAT_TXT], true)) {
            $errors[] = (string)__('Choose a format.');
        } else {
            try {
                $this->fileLocator->validate($feed->getPath(), $feed->getFilename(), $feed->getFormat());
            } catch (LocalizedException $e) {
                $errors[] = $e->getMessage();
            }
            try {
                $this->rendererProvider->get($feed->getFormat())->compile($feed);
            } catch (LocalizedException $e) {
                $errors[] = (string)__('Template: %1', $e->getMessage());
            }
        }
        if (!isset([FeedInterface::MODE_PARENT => 1, FeedInterface::MODE_CHILD => 1, FeedInterface::MODE_BOTH => 1][$feed->getParentChildMode()])) {
            $errors[] = (string)__('Choose how configurable products are exported.');
        }
        foreach ($feed->getConditions() as $index => $condition) {
            if ($condition['attribute'] !== '' && !isset(ProductProvider::OPERATORS[$condition['operator']])) {
                $errors[] = (string)__('Condition %1: choose an operator.', $index + 1);
            }
        }
        try {
            $this->nextRunCalculator->calculate($feed);
        } catch (LocalizedException $e) {
            $errors[] = (string)__('Schedule: %1', $e->getMessage());
        }
        if ($feed->isFtpEnabled() && trim((string)$feed->getData(FeedInterface::FTP_HOST)) === '') {
            $errors[] = (string)__('Enter the FTP/SFTP host or switch the upload off.');
        }
        $duplicate = $this->findFeedWithSameFile($feed);
        if ($duplicate !== null) {
            $errors[] = (string)__('The feed "%1" already writes to this file. Choose another file name.', $duplicate);
        }
        return $errors;
    }

    private function findFeedWithSameFile(FeedInterface $feed): ?string
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(FeedInterface::PATH, $this->fileLocator->normalisePath($feed->getPath()))
            ->addFieldToFilter(FeedInterface::FILENAME, $feed->getFilename());
        if ($feed->getId()) {
            $collection->addFieldToFilter('feed_id', ['neq' => (int)$feed->getId()]);
        }
        $other = $collection->setPageSize(1)->getFirstItem();
        return $other->getId() ? (string)$other->getData('name') : null;
    }
}
