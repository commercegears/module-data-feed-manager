<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Api\FeedRepositoryInterface;
use CommerceGears\DataFeed\Model\FeedFactory;

/**
 * Copies a feed (disabled, with a new file name) so it can be adapted.
 */
class Duplicate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_DataFeed::feeds';

    private const RESET_FIELDS = [
        FeedInterface::FEED_ID, FeedInterface::NEXT_RUN_AT, FeedInterface::GENERATED_AT, FeedInterface::GENERATED_ITEMS,
        FeedInterface::GENERATED_SIZE, FeedInterface::GENERATED_STATUS, FeedInterface::GENERATED_MESSAGE,
        'created_at', 'updated_at',
    ];

    public function __construct(
        Action\Context $context,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly FeedFactory $feedFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        try {
            $source = $this->feedRepository->getById((int)$this->getRequest()->getParam('id'));
            $data = $source->getData();
            foreach (self::RESET_FIELDS as $field) {
                unset($data[$field]);
            }
            $copy = $this->feedFactory->create()->setData($data);
            $copy->setName(__('%1 (copy)', $source->getName())->render());
            $copy->setIsActive(false);
            $info = pathinfo($source->getFilename());
            $copy->setFilename(($info['filename'] ?? 'feed') . '_copy_' . date('His') . '.' . ($info['extension'] ?? 'xml'));
            $this->feedRepository->save($copy);
            $this->messageManager->addSuccessMessage(__('The feed was copied. The copy is disabled until you enable it.'));
            return $redirect->setPath('*/*/edit', ['id' => $copy->getId()]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('*/*/');
    }
}
