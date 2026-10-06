<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Softaware\DataFeed\Model\FeedFactory;
use Softaware\DataFeed\Model\Generator\FileLocator;

/**
 * File name with a link to the public URL once the file exists.
 */
class FeedFile extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly FileLocator $fileLocator,
        private readonly FeedFactory $feedFactory,
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
            $feed = $this->feedFactory->create()->setData($item);
            $path = $this->fileLocator->normalisePath((string)($item['path'] ?? ''));
            $label = $this->escaper->escapeHtml($path . ($item['filename'] ?? ''));
            $url = $this->fileLocator->getPublicUrl($feed);
            if ($url && is_file($this->fileLocator->getAbsolutePath($feed))) {
                $html = '<a href="' . $this->escaper->escapeUrl($url) . '" target="_blank" rel="noopener">'
                    . $label . '</a>';
            } else {
                $html = $label;
                if (!$url) {
                    $html .= '<br/><small>' . $this->escaper->escapeHtml(__('not public')) . '</small>';
                }
            }
            $dataSource['data']['items'][$index][$name] = $html;
        }
        return $dataSource;
    }
}
