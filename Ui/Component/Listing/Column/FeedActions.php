<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class FeedActions extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $canGenerate = $this->authorization->isAllowed('Softaware_DataFeed::feed_generate');
        $canDelete = $this->authorization->isAllowed('Softaware_DataFeed::feed_delete');
        $canLog = $this->authorization->isAllowed('Softaware_DataFeed::log');
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['feed_id'])) {
                continue;
            }
            $id = (int)$item['feed_id'];
            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl('softaware_datafeed/feed/edit', ['id' => $id]),
                'label' => __('Edit'),
            ];
            if ($canGenerate) {
                $item[$name]['generate'] = [
                    'href' => $this->urlBuilder->getUrl('softaware_datafeed/feed/generate', ['id' => $id]),
                    'label' => __('Generate Now'),
                ];
            }
            if ($canLog) {
                $item[$name]['log'] = [
                    'href' => $this->urlBuilder->getUrl('softaware_datafeed/log/index', ['feed_id' => $id]),
                    'label' => __('View Log'),
                ];
            }
            $item[$name]['duplicate'] = [
                'href' => $this->urlBuilder->getUrl('softaware_datafeed/feed/duplicate', ['id' => $id]),
                'label' => __('Duplicate'),
                'post' => true,
            ];
            if ($canDelete) {
                $item[$name]['delete'] = [
                    'href' => $this->urlBuilder->getUrl('softaware_datafeed/feed/delete', ['id' => $id]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete Feed'),
                        'message' => __(
                            'Delete the feed "%1"? The generated file stays on the server.',
                            $this->escaper->escapeHtml((string)($item['name'] ?? ''))
                        ),
                    ],
                    'post' => true,
                ];
            }
        }
        return $dataSource;
    }
}
