<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Product;

use Magento\Catalog\Model\Product;
use Softaware\DataFeed\Model\Template\VariableProviderInterface;

/**
 * product.raw.<code>: the stored attribute value (option IDs instead of labels).
 */
class RawValues implements VariableProviderInterface
{
    public function __construct(private readonly Product $product)
    {
    }

    public function get(string $key): mixed
    {
        $value = $this->product->getData($key);
        return is_scalar($value) ? $value : null;
    }
}
