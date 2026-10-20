<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use CommerceGears\DataFeed\Api\FeedRepositoryInterface;
use CommerceGears\DataFeed\Model\Generator\FileLocator;
use CommerceGears\DataFeed\Model\ResourceModel\Feed\CollectionFactory;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_DataFeed::feed_delete';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly FileLocator $fileLocator
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $count = 0;
        $files = [];
        foreach ($this->filter->getCollection($this->collectionFactory->create()) as $feed) {
            $this->feedRepository->delete($feed);
            $count++;
            if ($feed->getFilename() !== '' && is_file($this->fileLocator->getAbsolutePath($feed))) {
                $files[] = $this->fileLocator->getRelativePath($feed);
            }
        }
        $this->messageManager->addSuccessMessage(__('%1 feed(s) deleted.', $count));
        // Generated files are kept (channels may still read them): say so, so nobody assumes they are gone.
        if ($files) {
            $this->messageManager->addNoticeMessage(__(
                'Their files were not deleted: %1. Delete them yourself if channels should no longer read them.',
                implode(', ', $files)
            ));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
