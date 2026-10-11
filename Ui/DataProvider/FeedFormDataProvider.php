<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Ui\DataProvider;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Model\Feed;
use CommerceGears\DataFeed\Model\FeedDataMapper;
use CommerceGears\DataFeed\Model\Generator\FileLocator;
use CommerceGears\DataFeed\Model\Preset\PresetProvider;
use CommerceGears\DataFeed\Model\Product\ProductContext;
use CommerceGears\DataFeed\Model\ResourceModel\Feed\CollectionFactory;
use CommerceGears\DataFeed\Model\Template\Filters;
use CommerceGears\DataFeed\Ui\Component\Listing\Column\FileSize;

class FeedFormDataProvider extends AbstractDataProvider
{
    public const PERSISTOR_KEY = 'commercegears_datafeed_feed';

    /** @var array<string|int, array<string, mixed>>|null */
    private ?array $loadedData = null;

    /** @var array<string, mixed> saved values (before re-displaying rejected form input) */
    private array $savedData = [];

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly FeedDataMapper $mapper,
        private readonly PresetProvider $presetProvider,
        private readonly FileLocator $fileLocator,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        private readonly Random $random,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        $id = (int)$this->request->getParam($this->getRequestFieldName());
        if ($id) {
            /** @var Feed|null $feed */
            $feed = $this->collection->addFieldToFilter('feed_id', $id)->getFirstItem();
            if ($feed && $feed->getId()) {
                $this->loadedData[$id] = $this->mapper->toFormData($feed);
            }
        } else {
            $preset = $this->presetProvider->get((string)$this->request->getParam('preset')) ?? $this->getDefaults();
            $preset[FeedInterface::STORE_ID] = (string)$this->getDefaultStoreId();
            if (!empty($preset[FeedInterface::FILENAME])) {
                $preset[FeedInterface::FILENAME] = $this->withRandomSuffix((string)$preset[FeedInterface::FILENAME]);
            }
            $this->loadedData[''] = $this->mapper->presetToFormData($preset);
        }

        $first = reset($this->loadedData);
        $this->savedData = is_array($first) ? $first : [];
        $persisted = $this->dataPersistor->get(self::PERSISTOR_KEY);
        if (is_array($persisted) && $persisted) {
            $key = (int)($persisted['feed_id'] ?? 0) ?: '';
            $this->loadedData[$key] = array_merge($this->loadedData[$key] ?? [], $persisted);
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
        }
        return $this->loadedData;
    }

    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $this->getData();
        $id = (int)$this->request->getParam($this->getRequestFieldName());

        $meta['general']['children']['file_info']['arguments']['data']['config']['content']
            = $this->fileInfo($id, $this->savedData);
        $meta['schedule_section']['children']['schedule_info']['arguments']['data']['config']['content']
            = $this->scheduleInfo($this->savedData);
        // Template and Columns both stay in the (tabbed) layout; the "Format" field's switcher shows the one that
        // matches the format.
        $meta['content_section']['children']['reference_section']['children']['reference']['arguments']['data']
            ['config']['content'] = $this->reference();
        return $meta;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaults(): array
    {
        return [
            FeedInterface::IS_ACTIVE => 1,
            FeedInterface::FORMAT => FeedInterface::FORMAT_XML,
            FeedInterface::PATH => FileLocator::DEFAULT_PATH,
            FeedInterface::TEMPLATE => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<products>\n{% for product in products %}\n"
                . "  <product>\n    <sku>{{ product.sku }}</sku>\n    <name>{{ product.name }}</name>\n"
                . "    <price>{{ product.final_price | number_format(2) }}</price>\n    <url>{{ product.url }}</url>\n"
                . "  </product>\n{% endfor %}\n</products>\n",
            FeedInterface::FILTER_ENABLED_ONLY => 1,
            FeedInterface::FILTER_IN_STOCK_ONLY => 0,
            FeedInterface::FILTER_CATEGORY_MODE => 'include',
            FeedInterface::PARENT_CHILD_MODE => FeedInterface::MODE_PARENT,
            FeedInterface::SCHEDULE_TYPE => FeedInterface::SCHEDULE_MANUAL,
            FeedInterface::CSV_DELIMITER => ',',
            FeedInterface::CSV_ENCLOSURE => '"',
            FeedInterface::CSV_ENCODING => 'UTF-8',
            FeedInterface::CSV_INCLUDE_HEADER => 1,
            FeedInterface::CSV_LINE_ENDING => 'lf',
            FeedInterface::FTP_PROTOCOL => 'sftp',
            FeedInterface::FTP_PASSIVE => 1,
            FeedInterface::COLUMNS => [
                ['header' => 'sku', 'value' => '{{ product.sku }}', 'default' => ''],
                ['header' => 'name', 'value' => '{{ product.name }}', 'default' => ''],
                ['header' => 'price', 'value' => '{{ product.final_price | number_format(2) }}', 'default' => ''],
            ],
        ];
    }

    /**
     * New feeds get a file name that cannot be guessed (google_shopping_k3v9q2xa.xml), so only the channels
     * the merchant gives the URL to find the feed. The name can be changed in the form.
     */
    private function withRandomSuffix(string $filename): string
    {
        $info = pathinfo($filename);
        $suffix = $this->random->getRandomString(8, Random::CHARS_LOWERS . Random::CHARS_DIGITS);
        return ($info['filename'] ?? 'feed') . '_' . $suffix . (isset($info['extension']) ? '.' . $info['extension'] : '');
    }

    private function getDefaultStoreId(): int
    {
        $store = $this->storeManager->getDefaultStoreView();
        return $store ? (int)$store->getId() : 1;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function fileInfo(int $id, array $data): string
    {
        if (!$id) {
            return '<p>' . $this->escaper->escapeHtml(__('Save the feed, then use "Generate Now" or the Preview tab.')) . '</p>';
        }
        /** @var Feed $feed */
        $feed = $this->collection->getNewEmptyItem();
        $feed->setData([
            FeedInterface::FEED_ID => $id,
            FeedInterface::STORE_ID => $data[FeedInterface::STORE_ID] ?? 0,
            FeedInterface::PATH => $data[FeedInterface::PATH] ?? '',
            FeedInterface::FILENAME => $data[FeedInterface::FILENAME] ?? '',
        ]);
        $url = $this->fileLocator->getPublicUrl($feed);
        $exists = is_file($this->fileLocator->getAbsolutePath($feed));
        $rows = [];
        if ($url) {
            $link = $exists
                ? '<a href="' . $this->escaper->escapeUrl($url) . '" target="_blank" rel="noopener">' . $this->escaper->escapeHtml($url) . '</a>'
                : $this->escaper->escapeHtml($url) . ' <em>(' . $this->escaper->escapeHtml(__('not generated yet')) . ')</em>';
            $rows[] = [__('Feed URL'), $link];
        } else {
            // Relative to the Magento root: the server's directory layout is not shown in the admin.
            $rows[] = [__('File'), $this->escaper->escapeHtml($this->fileLocator->getRelativePath($feed))
                . ' <em>(' . $this->escaper->escapeHtml(
                    $exists ? __('not public') : __('not public, not generated yet')
                ) . ')</em>'];
        }
        if (!empty($data[FeedInterface::GENERATED_AT])) {
            $status = (string)($data[FeedInterface::GENERATED_STATUS] ?? '');
            $rows[] = [
                __('Last Generated'),
                $this->escaper->escapeHtml($this->timezone->formatDateTime(
                    (string)$data[FeedInterface::GENERATED_AT],
                    \IntlDateFormatter::MEDIUM,
                    \IntlDateFormatter::SHORT
                ))
                . ' &middot; <strong class="commercegears-datafeed-status-' . $this->escaper->escapeHtmlAttr($status) . '">'
                . $this->escaper->escapeHtml(ucfirst($status)) . '</strong>'
                . ' &middot; ' . $this->escaper->escapeHtml(rtrim((string)($data[FeedInterface::GENERATED_MESSAGE] ?? ''), '. '))
                . (isset($data[FeedInterface::GENERATED_SIZE]) && $data[FeedInterface::GENERATED_SIZE] !== null
                    ? ' &middot; ' . $this->escaper->escapeHtml(FileSize::format((int)$data[FeedInterface::GENERATED_SIZE]))
                    : '')
                . ' &middot; <a href="' . $this->escaper->escapeUrl($this->urlBuilder->getUrl('commercegears_datafeed/log/index', ['feed_id' => $id])) . '">'
                . $this->escaper->escapeHtml(__('View Log')) . '</a>',
            ];
        }
        $html = '<table class="commercegears-datafeed-facts">';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr><th>' . $this->escaper->escapeHtml($label) . '</th><td>' . $value . '</td></tr>';
        }
        return $html . '</table>';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function scheduleInfo(array $data): string
    {
        $next = (string)($data[FeedInterface::NEXT_RUN_AT] ?? '');
        $text = $next !== '' && ($data[FeedInterface::SCHEDULE_TYPE] ?? 'manual') !== 'manual'
            ? __('Next run: %1', $this->timezone->formatDateTime($next, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT))
            : __('Scheduled feeds are generated by the Magento cron (group commercegears_datafeed). The next run is calculated when you save.');
        return '<p>' . $this->escaper->escapeHtml($text) . '</p>';
    }

    private function reference(): string
    {
        $html = '<div class="commercegears-datafeed-ref-columns"><div><h4>' . $this->escaper->escapeHtml(__('Product variables'))
            . '</h4><p>' . $this->escaper->escapeHtml(__('Use as product.<name>, e.g. {{ product.final_price_incl_tax | price }}. In loops over variants or children the same names are available on the loop variable.'))
            . '</p><table class="data-grid commercegears-datafeed-ref">';
        foreach (ProductContext::VARIABLES as $name => $description) {
            $html .= '<tr><td><code>' . $this->escaper->escapeHtml($name) . '</code></td><td>'
                . $this->escaper->escapeHtml(__($description)) . '</td></tr>';
        }
        $html .= '</table><h4>' . $this->escaper->escapeHtml(__('Other variables')) . '</h4><table class="data-grid commercegears-datafeed-ref">';
        $other = [
            'store.name, store.url, store.code, store.locale, store.language, store.country, store.weight_unit' => 'Store view details',
            'feed.name, feed.currency, feed.url' => 'Feed details',
            'now' => 'Generation time (ISO 8601)',
            'loop.index, loop.first, loop.last, loop.length' => 'Inside {% for %} loops',
        ];
        foreach ($other as $name => $description) {
            $html .= '<tr><td><code>' . $this->escaper->escapeHtml($name) . '</code></td><td>'
                . $this->escaper->escapeHtml(__($description)) . '</td></tr>';
        }
        $html .= '</table></div><div><h4>' . $this->escaper->escapeHtml(__('Filters')) . '</h4><p>'
            . $this->escaper->escapeHtml(__('Chain with |, e.g. {{ product.description | strip_tags | clean | truncate(500) }}. Operators: and, or, not, ==, !=, <, >, <=, >=, in, ~ (join text), ?? (fallback), a ? b : c.'))
            . '</p><table class="data-grid commercegears-datafeed-ref">';
        foreach (Filters::DESCRIPTIONS as $name => $description) {
            $html .= '<tr><td><code>' . $this->escaper->escapeHtml($name) . '</code></td><td>'
                . $this->escaper->escapeHtml(__($description)) . '</td></tr>';
        }
        return $html . '</table></div></div>';
    }
}
