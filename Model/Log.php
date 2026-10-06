<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model;

use Magento\Framework\Model\AbstractModel;
use Softaware\DataFeed\Model\ResourceModel\Log as LogResource;

class Log extends AbstractModel
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_WARNING = 'warning';
    public const STATUS_ERROR = 'error';

    protected function _construct(): void
    {
        $this->_init(LogResource::class);
    }
}
