<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Generator;

use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Model\Generator\Renderer\RendererInterface;
use CommerceGears\DataFeed\Model\Product\Needs;
use CommerceGears\DataFeed\Model\Product\StoreContext;

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
