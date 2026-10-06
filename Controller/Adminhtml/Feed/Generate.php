<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Softaware\DataFeed\Api\FeedRepositoryInterface;

/**
 * Progress page: the browser runs the generation batch by batch (see GenerateStep).
 */
class Generate extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_DataFeed::feed_generate';

    public function __construct(
        Action\Context $context,
        private readonly FeedRepositoryInterface $feedRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        try {
            $feed = $this->feedRepository->getById((int)$this->getRequest()->getParam('id'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        /** @var \Magento\Framework\View\Result\Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Softaware_DataFeed::feeds');
        $page->getConfig()->getTitle()->prepend(__('Generate Feed: %1', $feed->getName()));
        $block = $page->getLayout()->getBlock('softaware_datafeed_generate');
        if ($block) {
            $block->setData('feed', $feed);
        }
        return $page;
    }
}
