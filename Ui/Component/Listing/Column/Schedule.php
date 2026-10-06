<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Softaware\DataFeed\Api\Data\FeedInterface;

class Schedule extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $dataSource['data']['items'][$index][$name] = match ((string)($item[FeedInterface::SCHEDULE_TYPE] ?? '')) {
                FeedInterface::SCHEDULE_TIMES_DAILY => (string)__('Daily %1', (string)($item[FeedInterface::SCHEDULE_TIMES] ?? '')),
                FeedInterface::SCHEDULE_CRON_EXPR => (string)__('Cron %1', (string)($item[FeedInterface::SCHEDULE_CRON] ?? '')),
                default => (string)__('Manual'),
            };
        }
        return $dataSource;
    }
}
