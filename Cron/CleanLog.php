<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Cron;

use CommerceGears\DataFeed\Model\Config;
use CommerceGears\DataFeed\Model\Generator\JobStorage;
use CommerceGears\DataFeed\Model\ResourceModel\Log as LogResource;

/**
 * Removes old generation log entries and marks abandoned runs as failed.
 */
class CleanLog
{
    public function __construct(
        private readonly Config $config,
        private readonly LogResource $logResource
    ) {
    }

    public function execute(): void
    {
        $this->logResource->failStaleRuns(gmdate('Y-m-d H:i:s', time() - JobStorage::STALE_AFTER - 3600));
        $days = $this->config->getLogRetentionDays();
        if ($days > 0) {
            $this->logResource->deleteOlderThan(gmdate('Y-m-d H:i:s', time() - $days * 86400));
        }
    }
}
