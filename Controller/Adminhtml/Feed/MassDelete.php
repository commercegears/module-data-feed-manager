<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Softaware\DataFeed\Api\FeedRepositoryInterface;
use Softaware\DataFeed\Model\ResourceModel\Feed\CollectionFactory;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_DataFeed::feed_delete';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly FeedRepositoryInterface $feedRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $count = 0;
        foreach ($this->filter->getCollection($this->collectionFactory->create()) as $feed) {
            $this->feedRepository->delete($feed);
            $count++;
        }
        $this->messageManager->addSuccessMessage(__('%1 feed(s) deleted.', $count));
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
