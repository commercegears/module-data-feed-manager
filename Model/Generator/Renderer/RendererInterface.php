<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator\Renderer;

use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Model\Product\ProductContext;
use Softaware\DataFeed\Model\Product\StoreContext;

/**
 * Turns products into feed text for one output format.
 */
interface RendererInterface
{
    /**
     * Compiles the feed template/columns. Throws on syntax errors.
     *
     * @throws LocalizedException
     */
    public function compile(FeedInterface $feed): void;

    /**
     * Property names used by the template (to decide which data to load).
     *
     * @return string[]
     */
    public function getReferencedKeys(): array;

    public function setStoreContext(StoreContext $store): void;

    public function renderHeader(): string;

    /**
     * Renders one item. Throws \RuntimeException with a readable message if the item is invalid.
     */
    public function renderItem(ProductContext $product): string;

    /**
     * Text written between two items (e.g. "," for JSON).
     */
    public function getSeparator(): string;

    public function renderFooter(): string;
}
