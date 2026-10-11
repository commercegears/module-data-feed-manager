<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Generator;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;

/**
 * Keeps the state of running generations in var/commercegears_datafeed/ (one JSON file per feed).
 * The file doubles as a lock: a feed cannot be generated twice at the same time.
 */
class JobStorage
{
    /** Seconds after which an unfinished job is considered abandoned. */
    public const STALE_AFTER = 900;

    /** A generation that wrote its state more recently than this is still active and cannot be replaced. */
    public const ACTIVE_WITHIN = 120;

    public function __construct(
        private readonly FileLocator $fileLocator,
        private readonly File $file
    ) {
    }

    public function getStatePath(int $feedId): string
    {
        return $this->fileLocator->getWorkDirectory() . 'job_' . $feedId . '.json';
    }

    public function getPartPath(int $feedId): string
    {
        return $this->fileLocator->getWorkDirectory() . 'feed_' . $feedId . '.part';
    }

    /**
     * @throws FileSystemException
     */
    public function save(Job $job): void
    {
        $this->file->createDirectory($this->fileLocator->getWorkDirectory(), 0775);
        $path = $this->getStatePath($job->feedId);
        $this->file->filePutContents($path . '.tmp', (string)json_encode($job->toArray()));
        $this->file->rename($path . '.tmp', $path);
    }

    public function load(int $feedId): ?Job
    {
        $path = $this->getStatePath($feedId);
        try {
            if (!$this->file->isExists($path)) {
                return null;
            }
            $data = json_decode((string)$this->file->fileGetContents($path), true);
        } catch (FileSystemException) {
            return null;
        }
        return is_array($data) && isset($data['feed_id']) ? Job::fromArray($data) : null;
    }

    /**
     * Whether another generation of this feed is in progress (state updated recently).
     */
    public function isLocked(int $feedId): bool
    {
        $path = $this->getStatePath($feedId);
        try {
            if (!$this->file->isExists($path)) {
                return false;
            }
            $stat = $this->file->stat($path);
        } catch (FileSystemException) {
            return false;
        }
        return time() - (int)$stat['mtime'] < self::STALE_AFTER;
    }

    /**
     * Seconds since the running generation of this feed last wrote its state, or null when none is running.
     */
    public function getSecondsSinceUpdate(int $feedId): ?int
    {
        $path = $this->getStatePath($feedId);
        try {
            if (!$this->file->isExists($path)) {
                return null;
            }
            $stat = $this->file->stat($path);
        } catch (FileSystemException) {
            return null;
        }
        return max(0, time() - (int)$stat['mtime']);
    }

    public function delete(int $feedId): void
    {
        foreach ([$this->getStatePath($feedId), $this->getPartPath($feedId)] as $path) {
            try {
                if ($this->file->isExists($path)) {
                    $this->file->deleteFile($path);
                }
            } catch (FileSystemException) {
                // Nothing to clean up.
            }
        }
    }
}
