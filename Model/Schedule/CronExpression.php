<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Schedule;

use Magento\Framework\Exception\LocalizedException;

/**
 * Five-field cron expression (minute hour day-of-month month day-of-week) with *, lists, ranges, steps
 * and month/day names. Day of month and day of week are OR-ed when both are restricted, as in cron.
 */
class CronExpression
{
    private const FIELDS = [
        ['minute', 0, 59],
        ['hour', 0, 23],
        ['day of month', 1, 31],
        ['month', 1, 12],
        ['day of week', 0, 7],
    ];

    private const NAMES = [
        3 => ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
            'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12],
        4 => ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6],
    ];

    /** @var array<int, array<int, true>> */
    private array $allowed = [];
    private bool $domRestricted = false;
    private bool $dowRestricted = false;

    /**
     * @throws LocalizedException
     */
    public function __construct(string $expression)
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];
        if (count($parts) !== 5) {
            throw new LocalizedException(
                __('A cron expression has five fields: minute hour day-of-month month day-of-week, e.g. "0 */6 * * *".')
            );
        }
        foreach ($parts as $index => $part) {
            $this->allowed[$index] = $this->parseField(strtolower($part), $index);
        }
        if (isset($this->allowed[4][7])) {
            $this->allowed[4][0] = true;
        }
        $this->domRestricted = $parts[2] !== '*';
        $this->dowRestricted = $parts[4] !== '*';
    }

    /**
     * First matching minute strictly after $after, searching up to five years ahead.
     */
    public function getNextRun(\DateTimeImmutable $after): ?\DateTimeImmutable
    {
        $time = $after->setTime((int)$after->format('H'), (int)$after->format('i'))->modify('+1 minute');
        $day = $time->setTime(0, 0);
        for ($i = 0; $i < 366 * 5; $i++) {
            if ($this->matchesDay($day)) {
                $startHour = $day->format('Y-m-d') === $time->format('Y-m-d') ? (int)$time->format('H') : 0;
                for ($hour = $startHour; $hour < 24; $hour++) {
                    if (!isset($this->allowed[1][$hour])) {
                        continue;
                    }
                    $startMinute = ($day->format('Y-m-d') === $time->format('Y-m-d') && $hour === (int)$time->format('H'))
                        ? (int)$time->format('i')
                        : 0;
                    for ($minute = $startMinute; $minute < 60; $minute++) {
                        if (isset($this->allowed[0][$minute])) {
                            return $day->setTime($hour, $minute);
                        }
                    }
                }
            }
            $day = $day->modify('+1 day');
        }
        return null;
    }

    private function matchesDay(\DateTimeImmutable $day): bool
    {
        if (!isset($this->allowed[3][(int)$day->format('n')])) {
            return false;
        }
        $dom = isset($this->allowed[2][(int)$day->format('j')]);
        $dow = isset($this->allowed[4][(int)$day->format('w')]);
        if ($this->domRestricted && $this->dowRestricted) {
            return $dom || $dow;
        }
        return $dom && $dow;
    }

    /**
     * @return array<int, true>
     * @throws LocalizedException
     */
    private function parseField(string $field, int $index): array
    {
        [$label, $min, $max] = self::FIELDS[$index];
        $result = [];
        foreach (explode(',', $field) as $item) {
            if (!preg_match('/^(\*|[a-z0-9]+(?:-[a-z0-9]+)?)(?:\/(\d+))?$/', $item, $m)) {
                throw new LocalizedException(__('Cron expression: "%1" is not valid for the %2 field.', $item, $label));
            }
            $step = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 1;
            if ($step < 1) {
                throw new LocalizedException(__('Cron expression: the step in "%1" must be at least 1.', $item));
            }
            if ($m[1] === '*') {
                $from = $min;
                $to = $max;
            } elseif (str_contains($m[1], '-')) {
                [$a, $b] = explode('-', $m[1]);
                $from = $this->value($a, $index, $label);
                $to = $this->value($b, $index, $label);
            } else {
                $from = $this->value($m[1], $index, $label);
                $to = $step > 1 ? $max : $from;
            }
            if ($from > $to) {
                throw new LocalizedException(__('Cron expression: the range "%1" is reversed.', $item));
            }
            for ($v = $from; $v <= $to; $v += $step) {
                $result[$v] = true;
            }
        }
        return $result;
    }

    /**
     * @throws LocalizedException
     */
    private function value(string $text, int $index, string $label): int
    {
        if (isset(self::NAMES[$index][$text])) {
            return self::NAMES[$index][$text];
        }
        [, $min, $max] = self::FIELDS[$index];
        if (!ctype_digit($text) || (int)$text < $min || (int)$text > $max) {
            throw new LocalizedException(
                __('Cron expression: %1 must be between %2 and %3, "%4" given.', $label, $min, $max, $text)
            );
        }
        return (int)$text;
    }
}
