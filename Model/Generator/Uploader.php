<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\FtpFactory;
use Magento\Framework\Filesystem\Io\SftpFactory;
use Softaware\DataFeed\Api\Data\FeedInterface;

/**
 * Uploads generated feeds to an FTP, FTPS or SFTP server.
 */
class Uploader
{
    public const PROTOCOLS = ['sftp' => 'SFTP', 'ftp' => 'FTP', 'ftps' => 'FTPS (explicit TLS)'];

    private const TIMEOUT = 20;

    public function __construct(
        private readonly FtpFactory $ftpFactory,
        private readonly SftpFactory $sftpFactory,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Connection settings of a feed, with the password decrypted.
     *
     * @return array{protocol: string, host: string, port: int, username: string, password: string, path: string, passive: bool}
     */
    public function getSettings(FeedInterface $feed): array
    {
        $password = (string)$feed->getData(FeedInterface::FTP_PASSWORD);
        return [
            'protocol' => (string)($feed->getData(FeedInterface::FTP_PROTOCOL) ?: 'sftp'),
            'host' => trim((string)$feed->getData(FeedInterface::FTP_HOST)),
            'port' => (int)$feed->getData(FeedInterface::FTP_PORT),
            'username' => trim((string)$feed->getData(FeedInterface::FTP_USERNAME)),
            'password' => $password !== '' ? $this->encryptor->decrypt($password) : '',
            'path' => trim((string)$feed->getData(FeedInterface::FTP_PATH)),
            'passive' => $feed->getData(FeedInterface::FTP_PASSIVE) === null || (bool)$feed->getData(FeedInterface::FTP_PASSIVE),
        ];
    }

    /**
     * @param array{protocol: string, host: string, port: int, username: string, password: string, path: string, passive: bool} $settings
     * @throws LocalizedException
     */
    public function upload(array $settings, string $localFile, string $remoteName): string
    {
        $this->validate($settings);
        $temporary = $remoteName . '.uploading';
        try {
            if ($settings['protocol'] === 'sftp') {
                $connection = $this->openSftp($settings);
                try {
                    $target = $this->remotePath($settings['path'], $remoteName);
                    $tmpTarget = $this->remotePath($settings['path'], $temporary);
                    if (!$connection->write($tmpTarget, $localFile)) {
                        throw new LocalizedException(__('The file could not be written to %1.', $tmpTarget));
                    }
                    $connection->rm($target);
                    if (!$connection->mv($tmpTarget, $target)) {
                        throw new LocalizedException(__('The uploaded file could not be renamed to %1.', $target));
                    }
                } finally {
                    $connection->close();
                }
            } else {
                $connection = $this->openFtp($settings);
                try {
                    if (!$connection->write($temporary, $localFile)) {
                        throw new LocalizedException(__('The file could not be written to %1.', $temporary));
                    }
                    $connection->rm($remoteName);
                    if (!$connection->mv($temporary, $remoteName)) {
                        throw new LocalizedException(__('The uploaded file could not be renamed to %1.', $remoteName));
                    }
                } finally {
                    $connection->close();
                }
            }
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new LocalizedException(__('Upload failed: %1', $e->getMessage()), $e);
        }
        return (string)__(
            'Uploaded to %1://%2/%3',
            $settings['protocol'],
            $settings['host'],
            ltrim($this->remotePath($settings['path'], $remoteName), '/')
        );
    }

    /**
     * Opens and closes a connection (and changes to the remote directory) to check the settings.
     *
     * @param array{protocol: string, host: string, port: int, username: string, password: string, path: string, passive: bool} $settings
     * @throws LocalizedException
     */
    public function test(array $settings): string
    {
        $this->validate($settings);
        try {
            if ($settings['protocol'] === 'sftp') {
                $connection = $this->openSftp($settings);
                try {
                    if ($settings['path'] !== '' && !$connection->cd($settings['path'])) {
                        throw new LocalizedException(__('Connected, but the remote directory "%1" does not exist.', $settings['path']));
                    }
                    $directory = (string)$connection->pwd();
                } finally {
                    $connection->close();
                }
            } else {
                $connection = $this->openFtp($settings);
                try {
                    $directory = (string)$connection->pwd();
                } finally {
                    $connection->close();
                }
            }
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new LocalizedException(__('Connection failed: %1', $e->getMessage()), $e);
        }
        return (string)__('Connection successful. Remote directory: %1', $directory ?: '/');
    }

    /**
     * @param array<string, mixed> $settings
     * @throws LocalizedException
     */
    private function validate(array $settings): void
    {
        if (!isset(self::PROTOCOLS[$settings['protocol']])) {
            throw new LocalizedException(__('Choose FTP, FTPS or SFTP.'));
        }
        if ($settings['host'] === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $settings['host'])) {
            throw new LocalizedException(__('Enter a valid host name.'));
        }
        if ($settings['protocol'] !== 'sftp' && !function_exists('ftp_connect')) {
            throw new LocalizedException(__('The PHP ftp extension is not installed on this server.'));
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function openSftp(array $settings): \Magento\Framework\Filesystem\Io\Sftp
    {
        $connection = $this->sftpFactory->create();
        $port = $settings['port'] ?: 22;
        $connection->open([
            'host' => $settings['host'] . ':' . $port,
            'username' => $settings['username'],
            'password' => $settings['password'],
            'timeout' => self::TIMEOUT,
        ]);
        return $connection;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function openFtp(array $settings): \Magento\Framework\Filesystem\Io\Ftp
    {
        $connection = $this->ftpFactory->create();
        $connection->open([
            'host' => $settings['host'],
            'port' => $settings['port'] ?: 21,
            'user' => $settings['username'],
            'password' => $settings['password'],
            'passive' => $settings['passive'],
            'ssl' => $settings['protocol'] === 'ftps',
            'path' => $settings['path'],
            'timeout' => self::TIMEOUT,
        ]);
        return $connection;
    }

    private function remotePath(string $directory, string $file): string
    {
        if ($directory === '') {
            return $file;
        }
        return rtrim($directory, '/') . '/' . $file;
    }
}
