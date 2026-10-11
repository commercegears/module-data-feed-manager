<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Product;

/**
 * Data preloaded for one batch of products.
 */
class BatchData
{
    /** @var array<int, array{qty: float, is_in_stock: int, stock_status: int}> */
    public array $stock = [];

    /** @var array<int, array{price: ?float, final_price: ?float, min_price: ?float, max_price: ?float}> */
    public array $prices = [];

    /** @var array<int, array<int, array>> */
    public array $tierPrices = [];

    /** @var array<int, int[]> */
    public array $categoryIds = [];

    /** @var array<int, array{count: int, summary: int}> */
    public array $reviews = [];

    /** @var array<int, ProductContext[]> parent ID => children */
    public array $children = [];
}
