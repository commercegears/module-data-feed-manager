<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model;

use Magento\Framework\Model\AbstractModel;
use CommerceGears\DataFeed\Model\ResourceModel\Log as LogResource;

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
