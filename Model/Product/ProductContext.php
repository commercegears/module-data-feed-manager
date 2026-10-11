<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use CommerceGears\DataFeed\Model\Template\VariableProviderInterface;

/**
 * The "product" variable of a feed template. Values are computed on first use and cached.
 */
class ProductContext implements VariableProviderInterface
{
    /**
     * Computed variables (documented in the admin reference). Anything else is read as a product attribute.
     */
    public const VARIABLES = [
        'id' => 'Product ID',
        'sku' => 'SKU',
        'type' => 'Product type (simple, configurable, …)',
        'attribute_set' => 'Attribute set name',
        'brand' => 'Brand: the brand attribute from the settings (default: brand, otherwise manufacturer), '
            . 'falls back to the parent; empty when neither has a value',
        'url' => 'Product URL (children that are not visible use the parent URL)',
        'image' => 'Main image URL (falls back to the parent image, then the placeholder)',
        'small_image' => 'Small image URL',
        'thumbnail' => 'Thumbnail URL',
        'images' => 'Additional image URLs (list, without the main image)',
        'all_images' => 'Main and additional image URLs (list)',
        'has_image' => '1 when the product has its own main image',
        'placeholder' => 'Placeholder image URL',
        'regular_price' => 'Regular price (alias: price); composites use the lowest child price',
        'final_price' => 'Final price after special price and catalogue price rules (guests)',
        'special_price' => 'Special price when active today, otherwise empty',
        'min_price' => 'Lowest price (composites)',
        'max_price' => 'Highest price (composites)',
        '*_incl_tax / *_excl_tax' => 'Any price with or without tax, e.g. final_price_incl_tax',
        'on_sale' => '1 when the final price is below the regular price',
        'tier_prices' => 'Tier prices for guests: list of {qty, price}',
        'qty' => 'Stock quantity',
        'is_in_stock' => '1 or 0 (stock status, composites take children into account)',
        'is_salable' => '1 when enabled and in stock',
        'availability' => 'in_stock or out_of_stock (Google format)',
        'availability_text' => 'in stock or out of stock (Meta/Pinterest format)',
        'category' => 'Deepest category name',
        'category_id' => 'Deepest category ID',
        'category_path' => 'Deepest category path, e.g. "Men > Tops > Jackets"',
        'categories' => 'All category names (list)',
        'category_paths' => 'All category paths (list)',
        'google_category_id' => 'Mapped Google product category ID',
        'google_category' => 'Mapped Google product category path',
        'parent' => 'Parent product (all variables), e.g. parent.name; empty for products without parent',
        'item_group_id' => 'Parent SKU for variants, otherwise empty',
        'variants' => 'Configurable variants (list of products)',
        'children' => 'Children of configurable, bundle and grouped products (list of products)',
        'reviews_count' => 'Number of approved reviews',
        'rating' => 'Average rating in percent (0-100)',
        'rating_5' => 'Average rating out of 5',
        'raw' => 'Stored value instead of the label, e.g. raw.color gives the option ID',
        '<attribute_code>' => 'Any product attribute, store view value; options as labels',
    ];

    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(
        private readonly Product $product,
        private readonly BatchData $batch,
        private readonly StoreContext $store,
        private readonly AttributeResolver $attributes,
        private readonly PriceCalculator $prices,
        private ?ProductContext $parent = null
    ) {
    }

    public function getId(): int
    {
        return (int)$this->product->getId();
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getTypeId(): string
    {
        return (string)$this->product->getTypeId();
    }

    public function getParent(): ?ProductContext
    {
        return $this->parent;
    }

    public function setParent(?ProductContext $parent): void
    {
        $this->parent = $parent;
        $this->cache = [];
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->resolve($key);
        }
        return $this->cache[$key];
    }

    private function resolve(string $key): mixed
    {
        $product = $this->product;
        $id = $this->getId();
        switch ($key) {
            case 'id':
            case 'entity_id':
                return $id;
            case 'sku':
                return (string)$product->getSku();
            case 'type':
            case 'type_id':
                return $this->getTypeId();
            case 'attribute_set_id':
                return (int)$product->getAttributeSetId();
            case 'attribute_set':
                return $this->store->attributeSets[(int)$product->getAttributeSetId()] ?? '';
            case 'url':
                return $this->getUrl();
            case 'image':
                return $this->getImage('image');
            case 'small_image':
            case 'thumbnail':
                return $this->store->imageUrl((string)$product->getData($key)) ?? $this->get('image');
            case 'has_image':
                return $this->store->imageUrl((string)$product->getData('image')) !== null ? 1 : 0;
            case 'placeholder':
                return $this->store->placeholderUrl;
            case 'images':
            case 'additional_images':
                $images = $this->getGalleryImages();
                if (!$images && $this->parent && !$this->get('has_image')) {
                    return $this->parent->get('images');
                }
                return $images;
            case 'all_images':
                return array_values(array_unique(array_merge([$this->get('image')], $this->get('images'))));
            case 'on_sale':
                $final = $this->get('final_price');
                $regular = $this->get('regular_price');
                return $final !== null && $regular !== null && $final < $regular ? 1 : 0;
            case 'tier_prices':
                return $this->prices->getTierPrices($product, $this->batch, $this->store);
            case 'qty':
                return $this->batch->stock[$id]['qty'] ?? 0.0;
            case 'is_in_stock':
                return $this->batch->stock[$id]['stock_status'] ?? 0;
            case 'is_salable':
            case 'salable':
                return (int)$product->getStatus() === 1 && $this->get('is_in_stock') ? 1 : 0;
            case 'availability':
                return $this->get('is_in_stock') ? 'in_stock' : 'out_of_stock';
            case 'availability_text':
                return $this->get('is_in_stock') ? 'in stock' : 'out of stock';
            case 'category_ids':
                return array_values(array_filter(
                    $this->batch->categoryIds[$id] ?? [],
                    fn (int $categoryId) => isset($this->store->categories[$categoryId])
                ));
            case 'category_id':
                return $this->getDeepestCategoryId();
            case 'category':
                $categoryId = $this->getDeepestCategoryId();
                return $categoryId ? $this->store->categories[$categoryId]['name'] : '';
            case 'category_path':
                $categoryId = $this->getDeepestCategoryId();
                return $categoryId ? $this->store->categories[$categoryId]['path'] : '';
            case 'categories':
                return array_values(array_unique(array_map(
                    fn (int $categoryId) => $this->store->categories[$categoryId]['name'],
                    $this->get('category_ids')
                )));
            case 'category_paths':
                return array_values(array_unique(array_map(
                    fn (int $categoryId) => $this->store->categories[$categoryId]['path'],
                    $this->get('category_ids')
                )));
            case 'google_category_id':
                return $this->getGoogleCategoryId();
            case 'google_category':
                $googleId = $this->getGoogleCategoryId();
                return $googleId ? ($this->store->taxonomyPaths[$googleId] ?? '') : '';
            case 'parent':
                return $this->parent;
            case 'item_group_id':
                return $this->parent && $this->parent->getTypeId() === 'configurable' ? $this->parent->get('sku') : '';
            case 'variants':
                return $this->getTypeId() === 'configurable' ? ($this->batch->children[$id] ?? []) : [];
            case 'children':
                return $this->batch->children[$id] ?? [];
            case 'reviews_count':
                return $this->batch->reviews[$id]['count'] ?? 0;
            case 'rating':
                return $this->batch->reviews[$id]['summary'] ?? 0;
            case 'rating_5':
                $summary = $this->batch->reviews[$id]['summary'] ?? 0;
                return round($summary / 20, 1);
            case 'currency':
                return $this->store->currency;
            case 'raw':
                return new RawValues($product);
            case 'brand':
                return $this->getBrand();
        }

        if (preg_match('/^(price|regular_price|final_price|special_price|min_price|max_price)(?:_(incl|excl)_tax)?$/', $key, $m)) {
            return $this->prices->get($product, $this->batch, $this->store, $m[1], ($m[2] ?? '') ?: null);
        }

        $value = $product->getData($key);
        if ($this->attributes->isAttribute($key)) {
            return $this->attributes->getDisplayValue($key, $value, $this->store->storeId);
        }
        return is_scalar($value) ? $value : null;
    }

    private function getBrand(): mixed
    {
        $code = $this->store->brandAttribute;
        $value = $code === null
            ? null
            : $this->attributes->getDisplayValue($code, $this->product->getData($code), $this->store->storeId);
        if (is_string($value)) {
            $value = trim($value);
        }
        if ($value === null || $value === '' || !is_scalar($value)) {
            return $this->parent ? $this->parent->get('brand') : null;
        }
        return $value;
    }

    private function getUrl(): string
    {
        $visible = (int)$this->product->getVisibility() !== Visibility::VISIBILITY_NOT_VISIBLE;
        if (!$visible && $this->parent) {
            return (string)$this->parent->get('url');
        }
        $requestPath = (string)$this->product->getData('request_path');
        if ($requestPath !== '') {
            return $this->store->linkBaseUrl . ltrim($requestPath, '/');
        }
        return $this->store->linkBaseUrl . 'catalog/product/view/id/' . $this->getId() . '/';
    }

    private function getImage(string $role): string
    {
        $url = $this->store->imageUrl((string)$this->product->getData($role));
        if ($url !== null) {
            return $url;
        }
        if ($this->parent) {
            return (string)$this->parent->get($role);
        }
        return $this->store->placeholderUrl;
    }

    /**
     * @return string[]
     */
    private function getGalleryImages(): array
    {
        $gallery = $this->product->getData('media_gallery');
        $images = is_array($gallery) ? ($gallery['images'] ?? []) : [];
        if (!is_array($images) || !$images) {
            return [];
        }
        usort($images, static fn ($a, $b) => (int)($a['position'] ?? 0) <=> (int)($b['position'] ?? 0));
        $main = (string)$this->product->getData('image');
        $urls = [];
        foreach ($images as $image) {
            if (!empty($image['disabled']) || !empty($image['removed'])
                || (isset($image['media_type']) && $image['media_type'] !== 'image')
                || empty($image['file']) || $image['file'] === $main
            ) {
                continue;
            }
            $url = $this->store->imageUrl((string)$image['file']);
            if ($url !== null) {
                $urls[$url] = $url;
            }
        }
        return array_values($urls);
    }

    private function getDeepestCategoryId(): ?int
    {
        $best = null;
        $bestLevel = -1;
        foreach ($this->get('category_ids') as $categoryId) {
            $level = $this->store->categories[$categoryId]['level'];
            if ($level > $bestLevel) {
                $best = $categoryId;
                $bestLevel = $level;
            }
        }
        if ($best === null && $this->parent) {
            return $this->parent->get('category_id');
        }
        return $best;
    }

    private function getGoogleCategoryId(): ?int
    {
        $categoryIds = $this->get('category_ids');
        if (!$categoryIds && $this->parent) {
            return $this->parent->get('google_category_id');
        }
        usort(
            $categoryIds,
            fn (int $a, int $b) => $this->store->categories[$b]['level'] <=> $this->store->categories[$a]['level']
        );
        foreach ($categoryIds as $categoryId) {
            $googleId = $this->store->googleCategoryFor($categoryId);
            if ($googleId) {
                return $googleId;
            }
        }
        return null;
    }
}
