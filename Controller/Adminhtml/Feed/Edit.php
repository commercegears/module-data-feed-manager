<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use CommerceGears\DataFeed\Api\FeedRepositoryInterface;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_DataFeed::feeds';

    public function __construct(
        Action\Context $context,
        private readonly FeedRepositoryInterface $feedRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('id');
        try {
            $feed = $this->feedRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        /** @var \Magento\Framework\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('CommerceGears_DataFeed::feeds');
        $page->getConfig()->getTitle()->prepend(__('Feed: %1', $feed->getName()));
        return $page;
    }
}
