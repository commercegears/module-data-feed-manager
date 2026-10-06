<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Psr\Log\LoggerInterface;
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Api\FeedGeneratorInterface;
use Softaware\DataFeed\Api\FeedRepositoryInterface;
use Softaware\DataFeed\Model\Config;
use Softaware\DataFeed\Model\Log;
use Softaware\DataFeed\Model\Product\AttributeResolver;
use Softaware\DataFeed\Model\Product\Needs;
use Softaware\DataFeed\Model\Product\ProductProvider;
use Softaware\DataFeed\Model\Product\StoreContextBuilder;
use Softaware\DataFeed\Model\ResourceModel\Feed as FeedResource;
use Softaware\DataFeed\Model\ResourceModel\Log as LogResource;

/**
 * Batched feed generation: start() writes the header, step() appends one batch of products,
 * finish() writes the footer, validates, publishes the file and uploads it.
 * Cron and CLI run all steps in one process; the admin runs one step per request.
 */
class Generator implements FeedGeneratorInterface
{
    /** @var array<int, Session> */
    private array $sessions = [];

    public function __construct(
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly FeedResource $feedResource,
        private readonly LogResource $logResource,
        private readonly StoreContextBuilder $storeContextBuilder,
        private readonly ProductProvider $productProvider,
        private readonly RendererProvider $rendererProvider,
        private readonly AttributeResolver $attributeResolver,
        private readonly JobStorage $jobStorage,
        private readonly FileLocator $fileLocator,
        private readonly OutputValidator $outputValidator,
        private readonly Uploader $uploader,
        private readonly Config $config,
        private readonly File $file,
        private readonly LoggerInterface $logger
    ) {
    }

    public function generate(int $feedId, string $trigger, ?callable $progress = null): Result
    {
        $job = $this->start($feedId, $trigger);
        try {
            while (!$job->done) {
                $this->step($job);
                if ($progress) {
                    $progress($job->processed, $job->total);
                }
            }
        } catch (\Throwable $e) {
            $this->abort($job, $e);
            throw $e instanceof LocalizedException ? $e : new LocalizedException(__($e->getMessage()), $e);
        }
        return $this->finish($job);
    }

    /**
     * @throws LocalizedException
     */
    public function start(int $feedId, string $trigger, bool $force = false): Job
    {
        if (!$this->config->isEnabled()) {
            throw new LocalizedException(
                __('Data Feed Manager is disabled (Stores > Configuration > Softaware > Data Feed Manager).')
            );
        }
        $feed = $this->feedRepository->getById($feedId);
        if (!$force && $this->jobStorage->isLocked($feedId)) {
            throw new LocalizedException(
                __('Feed "%1" is already being generated. Try again in a few minutes.', $feed->getName())
            );
        }
        $sinceUpdate = $this->jobStorage->getSecondsSinceUpdate($feedId);
        if ($force && $sinceUpdate !== null && $sinceUpdate < JobStorage::ACTIVE_WITHIN) {
            // Replacing a run that is still writing (cron, CLI or another tab) would mix two files.
            throw new LocalizedException(__(
                'Feed "%1" is being generated right now (last progress %2 seconds ago). Wait until it has finished.',
                $feed->getName(),
                $sinceUpdate
            ));
        }
        $this->jobStorage->delete($feedId);
        $this->fileLocator->validate($feed->getPath(), $feed->getFilename(), $feed->getFormat());

        $session = $this->createSession($feed);
        $this->sessions[$feedId] = $session;
        $total = $this->productProvider->count($feed, $session->store);

        $logId = $this->createLog($feedId, $trigger);
        $job = new Job($feedId, $logId, $trigger, microtime(true), $this->jobStorage->getPartPath($feedId));
        $job->total = $total;
        try {
            $this->file->createDirectory($this->fileLocator->getWorkDirectory(), 0775);
            $this->file->filePutContents($job->partFile, $session->renderer->renderHeader());
            $job->headerWritten = true;
            $this->jobStorage->save($job);
        } catch (\Throwable $e) {
            $this->abort($job, $e);
            throw new LocalizedException(__('The feed could not be started: %1', $e->getMessage()), $e);
        }
        return $job;
    }

    /**
     * Processes one batch of products.
     *
     * @throws LocalizedException
     */
    public function step(Job $job): Job
    {
        if ($job->done) {
            return $job;
        }
        $session = $this->getSession($job->feedId);
        $limit = $this->config->getBatchSize();
        $batch = $this->productProvider->getBatch($session->feed, $session->store, $session->needs, $job->lastId, $limit);

        if ($batch['processed'] > 0) {
            $handle = fopen($job->partFile, 'ab');
            if ($handle === false) {
                throw new LocalizedException(__('The partial feed file %1 cannot be written.', $job->partFile));
            }
            try {
                foreach ($batch['items'] as $product) {
                    try {
                        $text = $session->renderer->renderItem($product);
                    } catch (\Throwable $e) {
                        $job->addError(sprintf('SKU %s: %s', $product->get('sku'), $e->getMessage()));
                        continue;
                    }
                    if ($text === '') {
                        continue;
                    }
                    if ($job->items > 0) {
                        $text = $session->renderer->getSeparator() . $text;
                    }
                    fwrite($handle, $text);
                    $job->items++;
                }
            } finally {
                fclose($handle);
            }
            $job->lastId = $batch['last_id'];
            $job->processed += $batch['processed'];
        }
        $job->done = $batch['processed'] < $limit;
        $this->jobStorage->save($job);
        return $job;
    }

    /**
     * Writes the footer, validates and publishes the file.
     *
     * @throws LocalizedException
     */
    public function finish(Job $job): Result
    {
        $session = $this->getSession($job->feedId);
        $feed = $session->feed;
        $messages = $job->messages;
        if ($job->errors > count($job->messages)) {
            $messages[] = (string)__('… and %1 more errors.', $job->errors - count($job->messages));
        }
        $target = $this->fileLocator->getAbsolutePath($feed);
        $status = $job->errors ? Log::STATUS_WARNING : Log::STATUS_SUCCESS;
        $size = 0;
        try {
            file_put_contents($job->partFile, $session->renderer->renderFooter(), FILE_APPEND);
            $error = $this->outputValidator->validateFile($job->partFile, $feed->getFormat());
            if ($error !== null) {
                $status = Log::STATUS_ERROR;
                array_unshift($messages, (string)__('The file was not published because it is invalid. %1', $error));
            } else {
                $this->publish($job->partFile, $target);
                $size = (int)filesize($target);
                if ($feed->isFtpEnabled()) {
                    try {
                        $settings = $this->uploader->getSettings($feed);
                        $messages[] = $this->uploader->upload($settings, $target, $feed->getFilename());
                        $hostKey = $this->uploader->getLastHostKey();
                        if ($settings['protocol'] === 'sftp' && $settings['host_key'] === '' && $hostKey !== null) {
                            // Trust on first use: later uploads stop if the server's key changes.
                            $this->feedResource->updateFields($job->feedId, [FeedInterface::FTP_HOST_KEY => $hostKey]);
                            $messages[] = (string)__('SFTP host key %1 saved with the feed.', $hostKey);
                        }
                    } catch (LocalizedException $e) {
                        $status = Log::STATUS_WARNING;
                        array_unshift($messages, (string)__('Upload failed: %1', $e->getMessage()));
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->abort($job, $e);
            throw new LocalizedException(__('The feed could not be completed: %1', $e->getMessage()), $e);
        }

        $duration = microtime(true) - $job->startedAt;
        $summary = $status === Log::STATUS_ERROR
            ? (string)($messages[0] ?? __('Generation failed.'))
            : (string)__('%1 items, %2 errors.', $job->items, $job->errors);
        $this->feedResource->updateFields($job->feedId, [
            FeedInterface::GENERATED_AT => gmdate('Y-m-d H:i:s'),
            FeedInterface::GENERATED_ITEMS => $job->items,
            FeedInterface::GENERATED_SIZE => $status === Log::STATUS_ERROR ? null : $size,
            FeedInterface::GENERATED_STATUS => $status,
            FeedInterface::GENERATED_MESSAGE => mb_substr($summary, 0, 1000),
        ]);
        $this->updateLog($job->logId, [
            'status' => $status,
            'finished_at' => gmdate('Y-m-d H:i:s'),
            'duration' => round($duration, 2),
            'items' => $job->items,
            'error_count' => $job->errors,
            'file_size' => $size ?: null,
            'messages' => $messages ? json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
        $this->jobStorage->delete($job->feedId);
        unset($this->sessions[$job->feedId]);

        return new Result(
            $job->feedId,
            $job->logId,
            $status,
            $job->items,
            $job->errors,
            $messages,
            $size,
            $status === Log::STATUS_ERROR ? null : $this->fileLocator->getPublicUrl($feed),
            $target,
            $duration
        );
    }

    /**
     * Stops a generation after a fatal error and records it.
     */
    public function abort(Job $job, \Throwable $error): void
    {
        $message = $error->getMessage();
        if (!$error instanceof LocalizedException) {
            $this->logger->error('Softaware_DataFeed: generation of feed ' . $job->feedId . ' failed', ['exception' => $error]);
        }
        try {
            $this->updateLog($job->logId, [
                'status' => Log::STATUS_ERROR,
                'finished_at' => gmdate('Y-m-d H:i:s'),
                'duration' => round(microtime(true) - $job->startedAt, 2),
                'items' => $job->items,
                'error_count' => $job->errors + 1,
                'messages' => json_encode(array_merge([$message], $job->messages), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $this->feedResource->updateFields($job->feedId, [
                FeedInterface::GENERATED_AT => gmdate('Y-m-d H:i:s'),
                FeedInterface::GENERATED_STATUS => Log::STATUS_ERROR,
                FeedInterface::GENERATED_MESSAGE => mb_substr($message, 0, 1000),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Softaware_DataFeed: could not record failed generation: ' . $e->getMessage());
        }
        $this->jobStorage->delete($job->feedId);
        unset($this->sessions[$job->feedId]);
    }

    /**
     * Compiles a feed and prepares its store data (used for generation and preview).
     *
     * @throws LocalizedException
     */
    public function createSession(FeedInterface $feed): Session
    {
        $renderer = $this->rendererProvider->get($feed->getFormat());
        $renderer->compile($feed);
        $store = $this->storeContextBuilder->build($feed);
        $renderer->setStoreContext($store);
        $needs = Needs::fromKeys(
            $renderer->getReferencedKeys(),
            $this->attributeResolver->getAllCodes(),
            $feed->getParentChildMode()
        );
        return new Session($feed, $store, $renderer, $needs);
    }

    /**
     * @throws LocalizedException
     */
    private function getSession(int $feedId): Session
    {
        if (!isset($this->sessions[$feedId])) {
            $this->sessions[$feedId] = $this->createSession($this->feedRepository->getById($feedId));
        }
        return $this->sessions[$feedId];
    }

    /**
     * Moves the finished file into place (atomic rename where possible).
     *
     * @throws LocalizedException
     */
    private function publish(string $source, string $target): void
    {
        $directory = dirname($target);
        $this->file->createDirectory($directory, 0775);
        if (!@rename($source, $target)) {
            if (!@copy($source, $target . '.tmp') || !@rename($target . '.tmp', $target)) {
                throw new LocalizedException(__('The feed file could not be written to %1.', $target));
            }
            @unlink($source);
        }
        @chmod($target, 0664);
    }

    private function createLog(int $feedId, string $trigger): int
    {
        $connection = $this->logResource->getConnection();
        $connection->insert($this->logResource->getMainTable(), [
            'feed_id' => $feedId,
            'trigger_source' => $trigger,
            'status' => Log::STATUS_RUNNING,
            'started_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return (int)$connection->lastInsertId($this->logResource->getMainTable());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function updateLog(int $logId, array $data): void
    {
        if ($logId <= 0) {
            return;
        }
        $this->logResource->getConnection()->update(
            $this->logResource->getMainTable(),
            $data,
            ['log_id = ?' => $logId]
        );
    }
}
