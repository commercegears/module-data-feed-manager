<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Generator;

/**
 * Outcome of a feed generation.
 */
class Result
{
    /**
     * @param string[] $messages
     */
    public function __construct(
        public readonly int $feedId,
        public readonly int $logId,
        public readonly string $status,
        public readonly int $items,
        public readonly int $errors,
        public readonly array $messages,
        public readonly int $fileSize,
        public readonly ?string $url,
        public readonly string $filePath,
        public readonly float $duration
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'feed_id' => $this->feedId,
            'log_id' => $this->logId,
            'status' => $this->status,
            'items' => $this->items,
            'errors' => $this->errors,
            'messages' => $this->messages,
            'file_size' => $this->fileSize,
            'url' => $this->url,
            'file_path' => $this->filePath,
            'duration' => round($this->duration, 2),
        ];
    }
}
