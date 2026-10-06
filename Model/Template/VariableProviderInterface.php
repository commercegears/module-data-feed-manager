<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Template;

/**
 * An object whose properties are resolved lazily by the template runtime (e.g. product.name).
 */
interface VariableProviderInterface
{
    public function get(string $key): mixed;
}
