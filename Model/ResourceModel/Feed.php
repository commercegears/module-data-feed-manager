<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Feed extends AbstractDb
{
    public const TABLE = 'commercegears_datafeed_feed';

    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'feed_id');
    }

    /**
     * Writes generation fields without touching the rest of the row (avoids overwriting concurrent admin edits).
     *
     * @param array<string, mixed> $data
     */
    public function updateFields(int $feedId, array $data): void
    {
        $this->getConnection()->update($this->getMainTable(), $data, ['feed_id = ?' => $feedId]);
    }
}
