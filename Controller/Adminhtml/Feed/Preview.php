<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Api\FeedRepositoryInterface;
use Softaware\DataFeed\Model\Config;
use Softaware\DataFeed\Model\FeedDataMapper;
use Softaware\DataFeed\Model\FeedFactory;
use Softaware\DataFeed\Model\Generator\Preview as PreviewService;

/**
 * Renders the first items of the feed as currently entered in the form (unsaved changes included).
 */
class Preview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_DataFeed::feeds';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly FeedFactory $feedFactory,
        private readonly FeedDataMapper $mapper,
        private readonly PreviewService $preview,
        private readonly Config $config
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
            unset($data['ftp_password']);
            $this->mapper->apply($feed, $data);
            $output = $this->preview->render($feed, $this->config->getPreviewLimit());
            return $result->setData(['ok' => true] + $output);
        } catch (LocalizedException $e) {
            return $result->setData(['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return $result->setData(['ok' => false, 'message' => (string)__('Preview failed: %1', $e->getMessage())]);
        }
    }
}
