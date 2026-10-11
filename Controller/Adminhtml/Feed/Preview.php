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
use CommerceGears\DataFeed\Model\Config;
use CommerceGears\DataFeed\Model\FeedDataMapper;
use CommerceGears\DataFeed\Model\FeedFactory;
use CommerceGears\DataFeed\Model\Generator\Preview as PreviewService;

/**
 * Renders the first items of the feed as currently entered in the form (unsaved changes included).
 */
class Preview extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_DataFeed::feeds';

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
            $output['templateLine'] = $this->findLine((string)($output['errors'][0] ?? ''), '/\bLine (\d+):/');
            $output['outputLine'] = $this->findLine((string)$output['validation'], '/\b(?:line|Row) (\d+)\b/');
            return $result->setData(['ok' => true] + $output);
        } catch (LocalizedException $e) {
            return $result->setData(['ok' => false, 'message' => $e->getMessage()] + $this->lineOf($e->getMessage()));
        } catch (\Throwable $e) {
            $message = (string)__('Preview failed: %1', $e->getMessage());
            return $result->setData(['ok' => false, 'message' => $message] + $this->lineOf($e->getMessage()));
        }
    }

    /**
     * Template line named in an error ("Line 12: …"), so the form can jump to it.
     *
     * @return array{templateLine?: int}
     */
    private function lineOf(string $message): array
    {
        $line = $this->findLine($message, '/\bLine (\d+):/');
        return $line ? ['templateLine' => $line] : [];
    }

    private function findLine(string $message, string $pattern): ?int
    {
        return preg_match($pattern, $message, $m) ? (int)$m[1] : null;
    }
}
