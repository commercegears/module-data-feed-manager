<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Api;

use Magento\Framework\Exception\LocalizedException;
use CommerceGears\DataFeed\Model\Generator\Result;

/**
 * Generates a feed file in one go (cron and CLI). The admin uses the step-wise job API instead.
 */
interface FeedGeneratorInterface
{
    public const TRIGGER_ADMIN = 'admin';
    public const TRIGGER_CRON = 'cron';
    public const TRIGGER_CLI = 'cli';

    /**
     * @param callable|null $progress function (int $processed, int $total): void
     * @throws LocalizedException
     */
    public function generate(int $feedId, string $trigger, ?callable $progress = null): Result;
}
