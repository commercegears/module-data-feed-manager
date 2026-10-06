<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Block\Adminhtml\Feed;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Ui\Component\Control\Container;
use Softaware\DataFeed\Model\Preset\PresetProvider;

/**
 * "Add Feed" split button: blank feed or one of the ready templates.
 */
class AddButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly PresetProvider $presetProvider
    ) {
    }

    public function getButtonData(): array
    {
        $options = [];
        foreach ($this->presetProvider->getOptions() as $code => $label) {
            $options[$code] = [
                'id' => 'softaware-datafeed-add-' . $code,
                'label' => __('From template: %1', $label),
                'onclick' => sprintf("setLocation('%s')", $this->urlBuilder->getUrl('*/*/new', ['preset' => $code])),
                'default' => false,
            ];
        }
        $options['blank'] = [
            'id' => 'softaware-datafeed-add-blank',
            'label' => __('Blank feed'),
            'onclick' => sprintf("setLocation('%s')", $this->urlBuilder->getUrl('*/*/new')),
        ];
        return [
            'id' => 'add_new_feed',
            'label' => __('Add Feed'),
            'class' => 'add primary',
            'button_class' => '',
            'class_name' => Container::SPLIT_BUTTON,
            'options' => $options,
            'onclick' => sprintf("setLocation('%s')", $this->urlBuilder->getUrl('*/*/new', ['preset' => 'google_shopping'])),
            'sort_order' => 10,
        ];
    }
}
