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
 * First messages of a log entry; the full list is in a collapsible block.
 */
class LogMessages extends Column
{
    private const VISIBLE = 3;

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
        foreach ($dataSource['data']['items'] ?? [] as $index => $item) {
            $messages = json_decode((string)($item[$name] ?? ''), true);
            if (!is_array($messages) || !$messages) {
                $dataSource['data']['items'][$index][$name] = '';
                continue;
            }
            $escaped = array_map(fn ($m) => $this->escaper->escapeHtml((string)$m), $messages);
            $html = implode('<br/>', array_slice($escaped, 0, self::VISIBLE));
            if (count($escaped) > self::VISIBLE) {
                $html .= '<details><summary>' . $this->escaper->escapeHtml(__('%1 more', count($escaped) - self::VISIBLE))
                    . '</summary>' . implode('<br/>', array_slice($escaped, self::VISIBLE)) . '</details>';
            }
            $dataSource['data']['items'][$index][$name] = $html;
        }
        return $dataSource;
    }
}
