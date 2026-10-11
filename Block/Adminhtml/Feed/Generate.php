<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Block\Adminhtml\Feed;

use Magento\Backend\Block\Template;
use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Model\Generator\FileLocator;
use CommerceGears\DataFeed\Model\Generator\JobStorage;

class Generate extends Template
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly FileLocator $fileLocator,
        private readonly JobStorage $jobStorage,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFeed(): ?FeedInterface
    {
        $feed = $this->getData('feed');
        return $feed instanceof FeedInterface ? $feed : null;
    }

    public function getStepUrl(): string
    {
        return $this->getUrl('*/*/generateStep');
    }

    public function getEditUrl(): string
    {
        return $this->getUrl('*/*/edit', ['id' => $this->getFeed()?->getId()]);
    }

    public function getLogUrl(): string
    {
        return $this->getUrl('*/log/index', ['feed_id' => $this->getFeed()?->getId()]);
    }

    public function getFilePath(): string
    {
        $feed = $this->getFeed();
        return $feed ? $this->fileLocator->normalisePath($feed->getPath()) . $feed->getFilename() : '';
    }

    public function isLocked(): bool
    {
        $feed = $this->getFeed();
        return $feed !== null && $this->jobStorage->isLocked((int)$feed->getId());
    }
}
