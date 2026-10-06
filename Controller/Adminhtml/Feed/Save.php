<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Api\FeedRepositoryInterface;
use Softaware\DataFeed\Model\FeedDataMapper;
use Softaware\DataFeed\Model\FeedFactory;
use Softaware\DataFeed\Model\FeedValidator;
use Softaware\DataFeed\Model\Schedule\NextRunCalculator;
use Softaware\DataFeed\Ui\DataProvider\FeedFormDataProvider;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_DataFeed::feeds';

    public function __construct(
        Action\Context $context,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly FeedFactory $feedFactory,
        private readonly FeedDataMapper $mapper,
        private readonly FeedValidator $validator,
        private readonly NextRunCalculator $nextRunCalculator,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = (array)$this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $id = (int)($data[FeedInterface::FEED_ID] ?? 0);
        try {
            $feed = $id ? $this->feedRepository->getById($id) : $this->feedFactory->create();
            $this->mapper->apply($feed, $data);
            $errors = $this->validator->validate($feed);
            if ($errors) {
                foreach (array_slice($errors, 1) as $error) {
                    $this->messageManager->addErrorMessage($error);
                }
                throw new LocalizedException(__($errors[0]));
            }
            $feed->setData(FeedInterface::NEXT_RUN_AT, $feed->isActive() ? $this->nextRunCalculator->calculate($feed) : null);
            $this->feedRepository->save($feed);
            $this->messageManager->addSuccessMessage(__('The feed "%1" has been saved.', $feed->getName()));
            $this->dataPersistor->clear(FeedFormDataProvider::PERSISTOR_KEY);

            $back = (string)($data['back'] ?? $this->getRequest()->getParam('back'));
            if ($back === 'generate' && $this->_authorization->isAllowed('Softaware_DataFeed::feed_generate')) {
                return $redirect->setPath('*/*/generate', ['id' => $feed->getId()]);
            }
            if ($back === 'close') {
                return $redirect->setPath('*/*/');
            }
            return $redirect->setPath('*/*/edit', ['id' => $feed->getId()]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the feed.'));
        }
        if (isset($data[FeedInterface::FTP_PASSWORD]) && $data[FeedInterface::FTP_PASSWORD] !== FeedDataMapper::PASSWORD_PLACEHOLDER) {
            $data[FeedInterface::FTP_PASSWORD] = '';
        }
        $this->dataPersistor->set(FeedFormDataProvider::PERSISTOR_KEY, $data);
        return $id
            ? $redirect->setPath('*/*/edit', ['id' => $id])
            : $redirect->setPath('*/*/new');
    }
}
