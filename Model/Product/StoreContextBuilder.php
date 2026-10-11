<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Product;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\View\Asset\PlaceholderFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Model\Generator\FileLocator;
use CommerceGears\DataFeed\Model\ResourceModel\CategoryMap;
use CommerceGears\DataFeed\Model\ResourceModel\Taxonomy;

class StoreContextBuilder
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CurrencyFactory $currencyFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly AttributeSetCollectionFactory $attributeSetCollectionFactory,
        private readonly CategoryMap $categoryMap,
        private readonly Taxonomy $taxonomy,
        private readonly PlaceholderFactory $placeholderFactory,
        private readonly Emulation $emulation,
        private readonly TimezoneInterface $timezone,
        private readonly FileLocator $fileLocator,
        private readonly LoggerInterface $logger,
        private readonly EavConfig $eavConfig,
        private readonly BrandAttribute $brandAttribute
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function build(FeedInterface $feed): StoreContext
    {
        try {
            $store = $this->storeManager->getStore($feed->getStoreId());
        } catch (\Exception) {
            throw new LocalizedException(__('The store view of feed "%1" no longer exists.', $feed->getName()));
        }
        $storeId = (int)$store->getId();
        $websiteId = (int)$store->getWebsiteId();

        $baseCurrency = (string)$store->getBaseCurrencyCode();
        $currency = $feed->getCurrencyCode() ?: (string)$store->getDefaultCurrencyCode();
        $rate = 1.0;
        if ($currency !== $baseCurrency) {
            $anyRate = $this->currencyFactory->create()->load($baseCurrency)->getAnyRate($currency);
            if (!$anyRate) {
                throw new LocalizedException(
                    __('There is no currency rate from %1 to %2. Add it in Stores > Currency Rates.', $baseCurrency, $currency)
                );
            }
            $rate = (float)$anyRate;
        }

        $categories = $this->loadCategories((int)$store->getRootCategoryId(), $storeId);
        $googleMap = $this->categoryMap->getMap();
        $taxonomyPaths = $this->taxonomy->getPaths(array_values(array_unique($googleMap)));

        $attributeSets = [];
        $sets = $this->attributeSetCollectionFactory->create()->setEntityTypeFilter(
            (int)$this->eavConfig->getEntityType(\Magento\Catalog\Model\Product::ENTITY)->getId()
        );
        foreach ($sets as $set) {
            $attributeSets[(int)$set->getId()] = (string)$set->getAttributeSetName();
        }

        $locale = (string)$this->scopeConfig->getValue('general/locale/code', ScopeInterface::SCOPE_STORE, $storeId);
        $linkBaseUrl = (string)$store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true);
        $mediaUrl = rtrim((string)$store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true), '/');

        $storeVariables = [
            'id' => $storeId,
            'code' => (string)$store->getCode(),
            'name' => (string)($this->scopeConfig->getValue('general/store_information/name', ScopeInterface::SCOPE_STORE, $storeId)
                ?: $store->getFrontendName() ?: $store->getName()),
            'view_name' => (string)$store->getName(),
            'url' => $linkBaseUrl,
            'media_url' => $mediaUrl . '/',
            'locale' => $locale,
            'language' => strtolower(substr($locale, 0, 2)),
            'country' => (string)$this->scopeConfig->getValue('general/country/default', ScopeInterface::SCOPE_STORE, $storeId),
            'currency' => $currency,
            'base_currency' => $baseCurrency,
            'weight_unit' => $this->scopeConfig->getValue('general/locale/weight_unit', ScopeInterface::SCOPE_STORE, $storeId) === 'kgs'
                ? 'kg'
                : 'lb',
        ];
        $now = $this->timezone->date();
        $feedVariables = [
            'id' => (int)$feed->getId(),
            'name' => $feed->getName(),
            'currency' => $currency,
            'filename' => $feed->getFilename(),
            'url' => (string)$this->fileLocator->getPublicUrl($feed),
            'generated_at' => $now->format(DATE_ATOM),
        ];

        return new StoreContext(
            $store,
            $storeId,
            $websiteId,
            $baseCurrency,
            $currency,
            $rate,
            $mediaUrl . '/catalog/product',
            $linkBaseUrl,
            $this->getPlaceholderUrl($storeId),
            $categories,
            $googleMap,
            $taxonomyPaths,
            $attributeSets,
            $storeVariables,
            $feedVariables,
            $now->format('Y-m-d'),
            $this->brandAttribute->getCode()
        );
    }

    /**
     * @return array<int, array{name: string, level: int, path_ids: int[], path: string}>
     */
    private function loadCategories(int $rootId, int $storeId): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'is_active'])
            ->addFieldToFilter('path', ['like' => '1/' . $rootId . '/%'])
            ->setOrder('level', 'ASC');

        $raw = [];
        foreach ($collection as $category) {
            $raw[(int)$category->getId()] = [
                'name' => trim((string)$category->getName()),
                'level' => (int)$category->getLevel(),
                'path_ids' => array_map('intval', explode('/', (string)$category->getPath())),
                'active' => (bool)$category->getIsActive(),
            ];
        }

        $result = [];
        foreach ($raw as $id => $category) {
            // Path below the root category, skipping "1/<root>".
            $names = [];
            $active = $category['active'];
            foreach (array_slice($category['path_ids'], 2) as $pathId) {
                if (!isset($raw[$pathId])) {
                    $active = false;
                    break;
                }
                $active = $active && $raw[$pathId]['active'];
                $names[] = $raw[$pathId]['name'];
            }
            if (!$active) {
                continue;
            }
            $result[$id] = [
                'name' => $category['name'],
                'level' => $category['level'],
                'path_ids' => array_slice($category['path_ids'], 2),
                'path' => implode(' > ', $names),
            ];
        }
        return $result;
    }

    private function getPlaceholderUrl(int $storeId): string
    {
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            return (string)$this->placeholderFactory->create(['type' => 'image'])->getUrl();
        } catch (\Throwable $e) {
            $this->logger->warning('CommerceGears_DataFeed: placeholder image URL could not be built: ' . $e->getMessage());
            return '';
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }
}
