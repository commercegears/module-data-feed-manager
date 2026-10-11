<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use CommerceGears\DataFeed\Api\FeedRepositoryInterface;
use CommerceGears\DataFeed\Model\FeedDataMapper;
use CommerceGears\DataFeed\Model\FeedFactory;
use CommerceGears\DataFeed\Model\Generator\Uploader;

/**
 * Tests the FTP/SFTP settings entered in the form (the saved password is used when unchanged).
 */
class TestConnection extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_DataFeed::feeds';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly FeedFactory $feedFactory,
        private readonly FeedDataMapper $mapper,
        private readonly Uploader $uploader
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            $data = json_decode((string)$this->getRequest()->getParam('feed'), true);
            if (!is_array($data)) {
                throw new LocalizedException(__('No feed data received.'));
            }
            $id = (int)($data['feed_id'] ?? 0);
            $feed = $id ? $this->feedRepository->getById($id) : $this->feedFactory->create();
            $this->mapper->assertSavedPasswordReusable($feed, $data);
            $this->mapper->apply($feed, $data);
            $message = $this->uploader->test($this->uploader->getSettings($feed));
            return $result->setData(['ok' => true, 'message' => $message]);
        } catch (LocalizedException $e) {
            return $result->setData(['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['ok' => false, 'message' => (string)__('Connection failed: %1', $e->getMessage())]);
        }
    }
}
