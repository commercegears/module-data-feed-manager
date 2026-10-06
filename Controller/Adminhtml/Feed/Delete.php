<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Api\FeedRepositoryInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_DataFeed::feed_delete';

    public function __construct(
        Action\Context $context,
        private readonly FeedRepositoryInterface $feedRepository
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
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect;
    }
}
