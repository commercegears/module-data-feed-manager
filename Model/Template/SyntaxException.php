<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Template;

use Magento\Framework\Exception\LocalizedException;

/**
 * Template could not be parsed. The message names the line.
 */
class SyntaxException extends LocalizedException
{
}
