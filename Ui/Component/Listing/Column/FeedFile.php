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
use CommerceGears\DataFeed\Model\FeedFactory;
use CommerceGears\DataFeed\Model\Generator\FileLocator;

/**
 * Public URL of the feed (linked once the file exists), or its location below the Magento root when not public.
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
            $url = $this->fileLocator->getPublicUrl($feed);
            $exists = is_file($this->fileLocator->getAbsolutePath($feed));
            if ($url) {
                // Public feeds show their URL (what the channel needs), linked once the file exists.
                $label = $this->escaper->escapeHtml($url);
                $html = $exists
                    ? '<a href="' . $this->escaper->escapeUrl($url) . '" target="_blank" rel="noopener">'
                        . $label . '</a>'
                    : $label . '<br/><small>' . $this->escaper->escapeHtml(__('not generated yet')) . '</small>';
            } else {
                // Private feeds show the location relative to the Magento root.
                $html = $this->escaper->escapeHtml($this->fileLocator->getRelativePath($feed))
                    . '<br/><small>' . $this->escaper->escapeHtml(__('not public')) . '</small>';
            }
            $dataSource['data']['items'][$index][$name] = $html;
        }
        return $dataSource;
    }
}
