<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use CommerceGears\DataFeed\Api\FeedRepositoryInterface;
use CommerceGears\DataFeed\Model\Generator\FileLocator;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_DataFeed::feed_delete';

    public function __construct(
        Action\Context $context,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly FileLocator $fileLocator
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        try {
            $feed = $this->feedRepository->getById((int)$this->getRequest()->getParam('id'));
            $this->feedRepository->delete($feed);
            $this->messageManager->addSuccessMessage(__('The feed "%1" has been deleted.', $feed->getName()));
            // The generated file is kept (channels may still read it): say so, so nobody assumes it is gone.
            if ($feed->getFilename() !== '' && is_file($this->fileLocator->getAbsolutePath($feed))) {
                $this->messageManager->addNoticeMessage(__(
                    'Its file %1 was not deleted. Delete it yourself if channels should no longer read it.',
                    $this->fileLocator->getRelativePath($feed)
                ));
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }
}
