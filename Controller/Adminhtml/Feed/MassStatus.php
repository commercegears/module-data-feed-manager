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
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Api\FeedRepositoryInterface;
use Softaware\DataFeed\Model\ResourceModel\Feed\CollectionFactory;
use Softaware\DataFeed\Model\Schedule\NextRunCalculator;

class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_DataFeed::feeds';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly NextRunCalculator $nextRunCalculator
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $active = (bool)$this->getRequest()->getParam('status');
        $count = 0;
        /** @var FeedInterface $feed */
        foreach ($this->filter->getCollection($this->collectionFactory->create()) as $feed) {
            $feed->setIsActive($active);
            try {
                $next = $active ? $this->nextRunCalculator->calculate($feed) : null;
            } catch (\Exception) {
                $next = null;
            }
            $feed->setData(FeedInterface::NEXT_RUN_AT, $next);
            $this->feedRepository->save($feed);
            $count++;
        }
        $this->messageManager->addSuccessMessage(
            $active ? __('%1 feed(s) enabled.', $count) : __('%1 feed(s) disabled.', $count)
        );
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
