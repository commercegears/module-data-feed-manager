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
use Psr\Log\LoggerInterface;
use Softaware\DataFeed\Api\FeedGeneratorInterface;
use Softaware\DataFeed\Model\Generator\Generator;
use Softaware\DataFeed\Model\Generator\JobStorage;

/**
 * One request per batch: action=start, then action=step until done (the last step also finishes the file).
 */
class GenerateStep extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_DataFeed::feed_generate';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Generator $generator,
        private readonly JobStorage $jobStorage,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $feedId = (int)$this->getRequest()->getParam('id');
        $action = (string)$this->getRequest()->getParam('step_action');
        $job = null;
        try {
            if ($action === 'start') {
                $job = $this->generator->start(
                    $feedId,
                    FeedGeneratorInterface::TRIGGER_ADMIN,
                    (bool)$this->getRequest()->getParam('force')
                );
                return $result->setData(['ok' => true, 'progress' => $job->getProgress()]);
            }
            $job = $this->jobStorage->load($feedId);
            if ($job === null) {
                throw new LocalizedException(__('The generation was interrupted. Start it again.'));
            }
            $this->generator->step($job);
            if (!$job->done) {
                return $result->setData(['ok' => true, 'progress' => $job->getProgress()]);
            }
            $outcome = $this->generator->finish($job);
            // The browser gets the public URL, not the server path of the file.
            $data = $outcome->toArray();
            unset($data['file_path']);
            return $result->setData(['ok' => true, 'progress' => $job->getProgress(), 'result' => $data]);
        } catch (\Throwable $e) {
            if ($job !== null && $action !== 'start') {
                $this->generator->abort($job, $e);
            }
            if (!$e instanceof LocalizedException) {
                $this->logger->error('Softaware_DataFeed: ' . $e->getMessage(), ['exception' => $e]);
            }
            return $result->setData([
                'ok' => false,
                'message' => $e instanceof LocalizedException ? $e->getMessage() : (string)__('Generation failed: %1', $e->getMessage()),
                'locked' => $action === 'start' && $this->jobStorage->isLocked($feedId),
            ]);
        }
    }
}
