<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Generation status as a coloured badge, with the last message as tooltip.
 */
class Status extends Column
{
    private const SEVERITY = [
        'success' => ['grid-severity-notice', 'Success'],
        'warning' => ['grid-severity-minor', 'Warning'],
        'error' => ['grid-severity-critical', 'Error'],
        'running' => ['grid-severity-minor', 'Running'],
    ];

    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        $name = $this->getData('name');
        $messageField = (string)($this->getData('config/messageField') ?? '');
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $status = (string)($item[$name] ?? '');
            if ($status === '') {
                $dataSource['data']['items'][$index][$name] = '<span class="commercegears-datafeed-muted">'
                    . $this->escaper->escapeHtml(__('Not generated')) . '</span>';
                continue;
            }
            [$class, $label] = self::SEVERITY[$status] ?? ['grid-severity-minor', ucfirst($status)];
            $title = $messageField !== '' ? (string)($item[$messageField] ?? '') : '';
            $dataSource['data']['items'][$index][$name] = '<span class="' . $class . '" title="'
                . $this->escaper->escapeHtmlAttr($title) . '"><span>' . $this->escaper->escapeHtml(__($label))
                . '</span></span>';
        }
        return $dataSource;
    }
}
