<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class FileSize extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $bytes = $item[$name] ?? null;
            $dataSource['data']['items'][$index][$name] = $bytes === null || $bytes === '' ? '' : self::format((int)$bytes);
        }
        return $dataSource;
    }

    public static function format(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return number_format($bytes / 1024 / 1024, 1) . ' MB';
    }
}
