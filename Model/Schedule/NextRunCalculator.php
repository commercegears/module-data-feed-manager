<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Schedule;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;
use Softaware\DataFeed\Api\Data\FeedInterface;

/**
 * Works out when a feed is due next. Daily times and cron expressions use the timezone of the feed's store view;
 * the result is stored in UTC.
 */
class NextRunCalculator
{
    public function __construct(private readonly TimezoneInterface $timezone)
    {
    }

    /**
     * @return string|null UTC "Y-m-d H:i:s", or null when the feed has no schedule
     * @throws LocalizedException
     */
    public function calculate(FeedInterface $feed, ?\DateTimeImmutable $after = null): ?string
    {
        $type = $feed->getScheduleType();
        if ($type === FeedInterface::SCHEDULE_MANUAL) {
            return null;
        }
        $zone = new \DateTimeZone(
            (string)($this->timezone->getConfigTimezone(ScopeInterface::SCOPE_STORE, $feed->getStoreId()) ?: 'UTC')
        );
        $now = ($after ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->setTimezone($zone);

        if ($type === FeedInterface::SCHEDULE_CRON_EXPR) {
            $next = (new CronExpression((string)$feed->getData(FeedInterface::SCHEDULE_CRON)))->getNextRun($now);
        } else {
            $next = null;
            foreach ($this->parseTimes((string)$feed->getData(FeedInterface::SCHEDULE_TIMES)) as [$hour, $minute]) {
                $candidate = $now->setTime($hour, $minute);
                if ($candidate <= $now) {
                    $candidate = $candidate->modify('+1 day')->setTime($hour, $minute);
                }
                if ($next === null || $candidate < $next) {
                    $next = $candidate;
                }
            }
        }
        return $next?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /**
     * @return array<int, array{0: int, 1: int}>
     * @throws LocalizedException
     */
    public function parseTimes(string $value): array
    {
        $times = [];
        foreach (preg_split('/[\s,;]+/', trim($value)) ?: [] as $item) {
            if ($item === '') {
                continue;
            }
            if (!preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)$/', $item, $m)) {
                throw new LocalizedException(__('"%1" is not a valid time. Use HH:MM, e.g. 06:30.', $item));
            }
            $times[(int)$m[1] * 60 + (int)$m[2]] = [(int)$m[1], (int)$m[2]];
        }
        if (!$times) {
            throw new LocalizedException(__('Enter at least one time, e.g. 06:00, 18:00.'));
        }
        ksort($times);
        return array_values($times);
    }
}
