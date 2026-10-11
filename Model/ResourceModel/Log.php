<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Log extends AbstractDb
{
    public const TABLE = 'softaware_datafeed_log';

    protected function _construct(): void
    {
        $this->_init(self::TABLE, 'log_id');
    }

    /**
     * Deletes entries older than the given UTC date time. Returns the number of rows removed.
     */
    public function deleteOlderThan(string $utcDateTime): int
    {
        return (int)$this->getConnection()->delete(
            $this->getMainTable(),
            ['started_at < ?' => $utcDateTime, 'status != ?' => 'running']
        );
    }

    /**
     * Marks runs that never finished (process killed, browser closed) as failed.
     */
    public function failStaleRuns(string $utcDateTime): int
    {
        return (int)$this->getConnection()->update(
            $this->getMainTable(),
            [
                'status' => 'error',
                // The run that did not finish is the error; items already counted as errors stay counted.
                'error_count' => new \Magento\Framework\DB\Sql\Expression('error_count + 1'),
                'messages' => json_encode(['Generation did not finish (process stopped or page closed).']),
            ],
            ['started_at < ?' => $utcDateTime, 'status = ?' => 'running']
        );
    }
}
