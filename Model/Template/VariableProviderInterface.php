<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Template;

/**
 * An object whose properties are resolved lazily by the template runtime (e.g. product.name).
 */
interface VariableProviderInterface
{
    public function get(string $key): mixed;
}
