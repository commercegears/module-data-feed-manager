<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Generator;

/**
 * State of a running generation. Stored between admin requests, so it holds only scalars.
 */
class Job
{
    public const MAX_MESSAGES = 100;

    public int $lastId = 0;
    public int $processed = 0;
    public int $total = 0;
    public int $items = 0;
    public int $errors = 0;
    /** @var string[] */
    public array $messages = [];
    public bool $done = false;
    public bool $headerWritten = false;

    public function __construct(
        public readonly int $feedId,
        public readonly int $logId,
        public readonly string $trigger,
        public readonly float $startedAt,
        public readonly string $partFile
    ) {
    }

    public function addError(string $message): void
    {
        $this->errors++;
        if (count($this->messages) < self::MAX_MESSAGES) {
            $this->messages[] = $message;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'feed_id' => $this->feedId,
            'log_id' => $this->logId,
            'trigger' => $this->trigger,
            'started_at' => $this->startedAt,
            'part_file' => $this->partFile,
            'last_id' => $this->lastId,
            'processed' => $this->processed,
            'total' => $this->total,
            'items' => $this->items,
            'errors' => $this->errors,
            'messages' => $this->messages,
            'done' => $this->done,
            'header_written' => $this->headerWritten,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $job = new self(
            (int)$data['feed_id'],
            (int)$data['log_id'],
            (string)$data['trigger'],
            (float)$data['started_at'],
            (string)$data['part_file']
        );
        $job->lastId = (int)($data['last_id'] ?? 0);
        $job->processed = (int)($data['processed'] ?? 0);
        $job->total = (int)($data['total'] ?? 0);
        $job->items = (int)($data['items'] ?? 0);
        $job->errors = (int)($data['errors'] ?? 0);
        $job->messages = array_map('strval', (array)($data['messages'] ?? []));
        $job->done = (bool)($data['done'] ?? false);
        $job->headerWritten = (bool)($data['header_written'] ?? false);
        return $job;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProgress(): array
    {
        return [
            'processed' => $this->processed,
            'total' => $this->total,
            'items' => $this->items,
            'errors' => $this->errors,
            'done' => $this->done,
            'percent' => $this->total > 0 ? min(100, (int)floor($this->processed * 100 / $this->total)) : ($this->done ? 100 : 0),
        ];
    }
}
