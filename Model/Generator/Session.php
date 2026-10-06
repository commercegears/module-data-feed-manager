<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator;

use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Model\Generator\Renderer\RendererInterface;
use Softaware\DataFeed\Model\Product\Needs;
use Softaware\DataFeed\Model\Product\StoreContext;

/**
 * A compiled feed ready to render products.
 */
class Session
{
    public function __construct(
        public readonly FeedInterface $feed,
        public readonly StoreContext $store,
        public readonly RendererInterface $renderer,
        public readonly Needs $needs
    ) {
    }
}
