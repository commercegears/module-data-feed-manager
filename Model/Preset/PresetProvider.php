<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Preset;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use CommerceGears\DataFeed\Api\Data\FeedInterface;

/**
 * Ready-made feed definitions offered under "Add Feed".
 */
class PresetProvider
{
    private const FILES_DIR = 'files/presets/';

    public function __construct(private readonly ModuleDirReader $moduleDirReader)
    {
    }

    /**
     * @return array<string, string> code => label
     */
    public function getOptions(): array
    {
        return [
            'google_shopping' => (string)__('Google Shopping (XML)'),
            'meta' => (string)__('Meta / Facebook catalogue (CSV)'),
            'pinterest' => (string)__('Pinterest catalogue (CSV)'),
            'bing' => (string)__('Microsoft / Bing Shopping (TXT)'),
            'generic_json' => (string)__('Generic JSON'),
        ];
    }

    /**
     * Feed data for a preset (form field names), or null for an unknown code.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $code): ?array
    {
        $common = [
            FeedInterface::IS_ACTIVE => 1,
            FeedInterface::PATH => 'pub/media/feeds/',
            FeedInterface::FILTER_ENABLED_ONLY => 1,
            FeedInterface::FILTER_IN_STOCK_ONLY => 0,
            FeedInterface::FILTER_VISIBILITY => [
                (string)Visibility::VISIBILITY_IN_CATALOG,
                (string)Visibility::VISIBILITY_IN_SEARCH,
                (string)Visibility::VISIBILITY_BOTH,
            ],
            FeedInterface::FILTER_CATEGORY_MODE => 'include',
            FeedInterface::SCHEDULE_TYPE => FeedInterface::SCHEDULE_TIMES_DAILY,
            FeedInterface::SCHEDULE_TIMES => '03:00',
            FeedInterface::CSV_ENCLOSURE => '"',
            FeedInterface::CSV_ENCODING => 'UTF-8',
            FeedInterface::CSV_INCLUDE_HEADER => 1,
            FeedInterface::CSV_LINE_ENDING => 'lf',
        ];
        switch ($code) {
            case 'google_shopping':
                return $common + [
                    FeedInterface::NAME => 'Google Shopping',
                    FeedInterface::FORMAT => FeedInterface::FORMAT_XML,
                    FeedInterface::FILENAME => 'google_shopping.xml',
                    FeedInterface::TEMPLATE => $this->readFile('google_shopping.xml'),
                    FeedInterface::PARENT_CHILD_MODE => FeedInterface::MODE_CHILD,
                    FeedInterface::CSV_DELIMITER => ',',
                ];
            case 'meta':
                return $common + [
                    FeedInterface::NAME => 'Meta catalogue',
                    FeedInterface::FORMAT => FeedInterface::FORMAT_CSV,
                    FeedInterface::FILENAME => 'meta_catalogue.csv',
                    FeedInterface::COLUMNS => $this->metaColumns(),
                    FeedInterface::PARENT_CHILD_MODE => FeedInterface::MODE_CHILD,
                    FeedInterface::CSV_DELIMITER => ',',
                ];
            case 'pinterest':
                return $common + [
                    FeedInterface::NAME => 'Pinterest catalogue',
                    FeedInterface::FORMAT => FeedInterface::FORMAT_CSV,
                    FeedInterface::FILENAME => 'pinterest.csv',
                    FeedInterface::COLUMNS => $this->pinterestColumns(),
                    FeedInterface::PARENT_CHILD_MODE => FeedInterface::MODE_CHILD,
                    FeedInterface::CSV_DELIMITER => ',',
                ];
            case 'bing':
                return [
                    FeedInterface::NAME => 'Microsoft Shopping',
                    FeedInterface::FORMAT => FeedInterface::FORMAT_TXT,
                    FeedInterface::FILENAME => 'bing_shopping.txt',
                    FeedInterface::COLUMNS => $this->bingColumns(),
                    FeedInterface::PARENT_CHILD_MODE => FeedInterface::MODE_CHILD,
                    FeedInterface::CSV_DELIMITER => 'tab',
                    FeedInterface::CSV_ENCLOSURE => 'none',
                ] + $common;
            case 'generic_json':
                return $common + [
                    FeedInterface::NAME => 'Product export (JSON)',
                    FeedInterface::FORMAT => FeedInterface::FORMAT_JSON,
                    FeedInterface::FILENAME => 'products.json',
                    FeedInterface::TEMPLATE => $this->readFile('generic.json'),
                    FeedInterface::PARENT_CHILD_MODE => FeedInterface::MODE_PARENT,
                    FeedInterface::CSV_DELIMITER => ',',
                ];
        }
        return null;
    }

    /**
     * @return array<int, array{header: string, value: string, default: string}>
     */
    private function metaColumns(): array
    {
        return [
            $this->column('id', '{{ product.sku }}'),
            $this->column('title', '{{ product.name | strip_tags | clean | truncate(200, \'\') }}'),
            $this->column(
                'description',
                '{{ (product.description ?? product.short_description ?? product.parent.description) | strip_tags | clean | truncate(9999, \'\') }}',
                '{{ product.name }}'
            ),
            $this->column('availability', '{{ product.availability_text }}'),
            $this->column('condition', 'new'),
            $this->column('price', '{{ product.regular_price_incl_tax | price }}'),
            $this->column('sale_price', '{% if product.on_sale %}{{ product.final_price_incl_tax | price }}{% endif %}'),
            $this->column('link', '{{ product.url }}'),
            $this->column('image_link', '{{ product.image }}'),
            $this->column('additional_image_link', '{{ product.images | slice(0, 20) | join(\',\') }}'),
            $this->column('brand', '{{ product.brand }}', '{{ store.name }}'),
            $this->column('item_group_id', '{{ product.item_group_id }}'),
            $this->column('google_product_category', '{{ product.google_category_id }}'),
            $this->column('product_type', '{{ product.category_path }}'),
            $this->column('color', '{{ product.color }}'),
            $this->column('size', '{{ product.size }}'),
            // Only a known stock quantity: bundles, grouped, downloadable and unmanaged stock have none (0 would
            // stop Meta selling an in-stock product).
            $this->column('quantity_to_sell_on_facebook', '{% if product.qty > 0 %}{{ product.qty | round }}{% endif %}'),
        ];
    }

    /**
     * @return array<int, array{header: string, value: string, default: string}>
     */
    private function pinterestColumns(): array
    {
        return [
            $this->column('id', '{{ product.sku }}'),
            $this->column('title', '{{ product.name | strip_tags | clean | truncate(500, \'\') }}'),
            $this->column(
                'description',
                '{{ (product.description ?? product.short_description ?? product.parent.description) | strip_tags | clean | truncate(10000, \'\') }}',
                '{{ product.name }}'
            ),
            $this->column('link', '{{ product.url }}'),
            $this->column('image_link', '{{ product.image }}'),
            $this->column('additional_image_link', '{{ product.images | slice(0, 10) | join(\',\') }}'),
            $this->column('price', '{{ product.regular_price_incl_tax | price }}'),
            $this->column('sale_price', '{% if product.on_sale %}{{ product.final_price_incl_tax | price }}{% endif %}'),
            $this->column('availability', '{{ product.availability_text }}'),
            $this->column('condition', 'new'),
            $this->column('brand', '{{ product.brand }}', '{{ store.name }}'),
            $this->column('google_product_category', '{{ product.google_category_id }}'),
            $this->column('product_type', '{{ product.category_path }}'),
            $this->column('item_group_id', '{{ product.item_group_id }}'),
            $this->column('color', '{{ product.color }}'),
            $this->column('size', '{{ product.size }}'),
        ];
    }

    /**
     * @return array<int, array{header: string, value: string, default: string}>
     */
    private function bingColumns(): array
    {
        return [
            $this->column('id', '{{ product.sku }}'),
            $this->column('title', '{{ product.name | strip_tags | clean | truncate(150, \'\') }}'),
            $this->column('link', '{{ product.url }}'),
            $this->column('price', '{{ product.regular_price_incl_tax | price }}'),
            $this->column('sale_price', '{% if product.on_sale %}{{ product.final_price_incl_tax | price }}{% endif %}'),
            $this->column(
                'description',
                '{{ (product.description ?? product.short_description ?? product.parent.description) | strip_tags | clean | truncate(5000, \'\') }}',
                '{{ product.name }}'
            ),
            $this->column('image_link', '{{ product.image }}'),
            $this->column('additional_image_link', '{{ product.images | slice(0, 10) | join(\',\') }}'),
            $this->column('availability', '{{ product.availability_text }}'),
            $this->column('condition', 'new'),
            $this->column('brand', '{{ product.brand }}', '{{ store.name }}'),
            $this->column('gtin', '{{ product.gtin }}'),
            $this->column('mpn', '{{ product.mpn }}'),
            $this->column('identifier_exists', '{{ product.gtin or product.mpn ? \'yes\' : \'no\' }}'),
            $this->column('product_category', '{{ product.google_category }}'),
            $this->column('product_type', '{{ product.category_path }}'),
            $this->column('item_group_id', '{{ product.item_group_id }}'),
            $this->column('color', '{{ product.color }}'),
            $this->column('size', '{{ product.size }}'),
        ];
    }

    /**
     * @return array{header: string, value: string, default: string}
     */
    private function column(string $header, string $value, string $default = ''): array
    {
        return ['header' => $header, 'value' => $value, 'default' => $default];
    }

    private function readFile(string $name): string
    {
        $path = $this->moduleDirReader->getModuleDir('', 'CommerceGears_DataFeed') . '/' . self::FILES_DIR . $name;
        return is_readable($path) ? (string)file_get_contents($path) : '';
    }
}
