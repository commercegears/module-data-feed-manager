<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Generator;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\FtpFactory;
use phpseclib3\Net\SFTP;
use CommerceGears\DataFeed\Api\Data\FeedInterface;

/**
 * Uploads generated feeds to an FTP, FTPS or SFTP server.
 */
class Uploader
{
    public const PROTOCOLS = ['sftp' => 'SFTP', 'ftp' => 'FTP', 'ftps' => 'FTPS (explicit TLS)'];

    private const TIMEOUT = 20;

    private ?string $lastHostKey = null;

    public function __construct(
        private readonly FtpFactory $ftpFactory,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Connection settings of a feed, with the password decrypted.
     *
     * @return array{protocol: string, host: string, port: int, username: string, password: string, path: string, passive: bool, host_key: string}
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
            'host_key' => trim((string)$feed->getData(FeedInterface::FTP_HOST_KEY)),
        ];
    }

    /**
     * @param array{protocol: string, host: string, port: int, username: string, password: string, path: string, passive: bool, host_key?: string} $settings
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
                    if (!$connection->put($tmpTarget, $localFile, SFTP::SOURCE_LOCAL_FILE)) {
                        throw new LocalizedException(__('The file could not be written to %1.', $tmpTarget));
                    }
                    $connection->delete($target, false);
                    if (!$connection->rename($tmpTarget, $target)) {
                        throw new LocalizedException(__('The uploaded file could not be renamed to %1.', $target));
                    }
                } finally {
                    $connection->disconnect();
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
     * @param array{protocol: string, host: string, port: int, username: string, password: string, path: string, passive: bool, host_key?: string} $settings
     * @throws LocalizedException
     */
    public function test(array $settings): string
    {
        $this->validate($settings);
        try {
            if ($settings['protocol'] === 'sftp') {
                $connection = $this->openSftp($settings);
                try {
                    if ($settings['path'] !== '' && !$connection->chdir($settings['path'])) {
                        throw new LocalizedException(__('Connected, but the remote directory "%1" does not exist.', $settings['path']));
                    }
                    $directory = (string)$connection->pwd();
                } finally {
                    $connection->disconnect();
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
        $message = (string)__('Connection successful. Remote directory: %1', $directory ?: '/');
        if ($settings['protocol'] === 'sftp' && $this->lastHostKey !== null) {
            $message .= ' ' . (($settings['host_key'] ?? '') !== ''
                ? __('The host key matches the saved fingerprint.')
                : __('Host key: %1 (saved with the feed on the first upload).', $this->lastHostKey));
        }
        return $message;
    }

    /**
     * SHA256 fingerprint of the SFTP host key seen on the last connection, as "SHA256:…" (OpenSSH format).
     */
    public function getLastHostKey(): ?string
    {
        return $this->lastHostKey;
    }

    /**
     * OpenSSH-style fingerprint of a public key line ("ssh-ed25519 AAAA…").
     */
    public static function fingerprint(string $publicKey): string
    {
        $parts = preg_split('/\s+/', trim($publicKey));
        $blob = base64_decode((string)($parts[1] ?? $parts[0] ?? ''), true);
        return 'SHA256:' . rtrim(base64_encode(hash('sha256', $blob === false ? '' : $blob, true)), '=');
    }

    /**
     * Accepts "SHA256:abc…", "abc…" or the output of ssh-keygen -l ("256 SHA256:abc… host (ED25519)").
     */
    public static function normaliseFingerprint(string $value): string
    {
        if (preg_match('/SHA256:([A-Za-z0-9+\/]+)=*/', $value, $m)) {
            return 'SHA256:' . $m[1];
        }
        $value = trim($value);
        return $value === '' ? '' : 'SHA256:' . rtrim($value, '=');
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
    private function openSftp(array $settings): SFTP
    {
        $this->lastHostKey = null;
        $host = trim($settings['host'], '[]');
        $connection = new SFTP($host, $settings['port'] ?: 22, self::TIMEOUT);
        // Check the server before the password is sent: the key must be signed correctly and, when a fingerprint
        // is saved with the feed, match it.
        $publicKey = $connection->getServerPublicHostKey();
        if (!is_string($publicKey) || $publicKey === '') {
            $connection->disconnect();
            throw new LocalizedException(
                __('Could not connect to %1 or verify its SFTP host key. %2', $host, (string)$connection->getLastError())
            );
        }
        $this->lastHostKey = self::fingerprint($publicKey);
        $expected = self::normaliseFingerprint((string)($settings['host_key'] ?? ''));
        if ($expected !== '' && !hash_equals($expected, $this->lastHostKey)) {
            $connection->disconnect();
            throw new LocalizedException(__(
                'The SFTP host key of %1 has changed (now %2, saved %3), so the password was not sent. '
                . 'If the server was really replaced, clear "SFTP Host Key" in the feed and save.',
                $host,
                $this->lastHostKey,
                $expected
            ));
        }
        if (!$connection->login($settings['username'], $settings['password'])) {
            $connection->disconnect();
            throw new LocalizedException(
                __('Unable to log in to %1 as %2.', $host, $settings['username'])
            );
        }
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
