<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Softaware\DataFeed\Api\Data\FeedInterface;

/**
 * Validates feed output locations and builds file paths and public URLs.
 * Feeds may only be written below pub/ (public) or var/ (private), never as executable files.
 */
class FileLocator
{
    public const DEFAULT_PATH = 'pub/media/feeds/';

    private const EXTENSIONS = [
        FeedInterface::FORMAT_XML => ['xml'],
        FeedInterface::FORMAT_JSON => ['json'],
        FeedInterface::FORMAT_CSV => ['csv', 'txt'],
        FeedInterface::FORMAT_TXT => ['txt', 'tsv', 'csv'],
    ];

    private const FORBIDDEN_PREFIXES = ['pub/static/', 'pub/errors/', 'pub/opt/'];

    public function __construct(
        private readonly DirectoryList $directoryList,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function normalisePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        $path = (string)preg_replace('#/{2,}#', '/', $path);
        $path = ltrim((string)preg_replace('#^(\./)+#', '', $path), '/');
        if ($path === '') {
            return self::DEFAULT_PATH;
        }
        return rtrim($path, '/') . '/';
    }

    /**
     * @throws LocalizedException
     */
    public function validate(string $path, string $filename, string $format): void
    {
        $path = $this->normalisePath($path);
        // No ".", ".." or hidden folders such as .well-known (files there have a special meaning for browsers,
        // apps and certificate authorities).
        if (!preg_match('#^[A-Za-z0-9_\-./]+$#', $path) || preg_match('#(^|/)\.#', $path)) {
            throw new LocalizedException(
                __('The path "%1" may contain only letters, digits, "-", "_" and "/", and no folder may start with ".".', $path)
            );
        }
        if (!str_starts_with($path, 'pub/') && !str_starts_with($path, 'var/')) {
            throw new LocalizedException(
                __('The path must be inside pub/ (public, e.g. pub/media/feeds/) or var/ (not public).')
            );
        }
        foreach (self::FORBIDDEN_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                throw new LocalizedException(__('Feeds cannot be written to %1.', $prefix));
            }
        }
        if (!preg_match('#^[A-Za-z0-9][A-Za-z0-9_\-.]{0,200}$#', $filename)) {
            throw new LocalizedException(
                __('The file name may contain only letters, digits, ".", "-" and "_".')
            );
        }
        $extension = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = self::EXTENSIONS[$format] ?? [];
        if (!in_array($extension, $allowed, true)) {
            throw new LocalizedException(
                __('The file name must end in .%1 for this format.', implode(' or .', $allowed))
            );
        }
    }

    public function getAbsolutePath(FeedInterface $feed): string
    {
        return $this->getAbsoluteDirectory($feed) . $feed->getFilename();
    }

    /**
     * Location of the feed file relative to the Magento root (e.g. var/export/feed.csv), for display in the admin
     * without revealing the server's directory layout.
     */
    public function getRelativePath(FeedInterface $feed): string
    {
        return $this->normalisePath($feed->getPath()) . $feed->getFilename();
    }

    /**
     * An absolute path below the Magento root as a path relative to it; other paths are reduced to the file name.
     */
    public function toDisplayPath(string $absolute): string
    {
        $root = rtrim($this->directoryList->getRoot(), '/') . '/';
        return str_starts_with($absolute, $root) ? substr($absolute, strlen($root)) : basename($absolute);
    }

    /**
     * Removes the Magento root directory from a message (e.g. a file system error), so it shows relative paths.
     */
    public function hideRoot(string $message): string
    {
        return str_replace(rtrim($this->directoryList->getRoot(), '/') . '/', '', $message);
    }

    public function getAbsoluteDirectory(FeedInterface $feed): string
    {
        return rtrim($this->directoryList->getRoot(), '/') . '/' . $this->normalisePath($feed->getPath());
    }

    /**
     * Working directory for partial files and job state.
     */
    public function getWorkDirectory(): string
    {
        return rtrim($this->directoryList->getPath(DirectoryList::VAR_DIR), '/') . '/softaware_datafeed/';
    }

    /**
     * Public URL of the feed file, or null when it is not in a public directory.
     */
    public function getPublicUrl(FeedInterface $feed): ?string
    {
        $path = $this->normalisePath($feed->getPath());
        if (!str_starts_with($path, 'pub/') || $feed->getFilename() === '') {
            return null;
        }
        try {
            $store = $this->storeManager->getStore($feed->getStoreId());
        } catch (\Exception) {
            $store = $this->storeManager->getDefaultStoreView();
        }
        if ($store === null) {
            return null;
        }
        if (str_starts_with($path, 'pub/media/')) {
            return $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true)
                . substr($path, strlen('pub/media/')) . rawurlencode($feed->getFilename());
        }
        return $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true)
            . substr($path, strlen('pub/')) . rawurlencode($feed->getFilename());
    }
}
