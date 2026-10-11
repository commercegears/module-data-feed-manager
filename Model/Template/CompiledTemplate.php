<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Template;

/**
 * Parsed template split around the top-level "for ... in products" loop.
 */
class CompiledTemplate
{
    /**
     * @param array<int, array> $header nodes before the item loop
     * @param array<int, array> $item nodes of the item loop body
     * @param array<int, array> $footer nodes after the item loop
     * @param string[] $referencedKeys property names used anywhere (a.b → "b"), for attribute selection
     */
    public function __construct(
        public readonly array $header,
        public readonly array $item,
        public readonly array $footer,
        public readonly string $itemVariable,
        public readonly array $referencedKeys,
        public readonly bool $hasItemLoop
    ) {
    }
}
