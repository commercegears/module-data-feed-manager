<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Template;

use Magento\Framework\Exception\LocalizedException;

/**
 * Template could not be parsed. The message names the line.
 */
class SyntaxException extends LocalizedException
{
}
