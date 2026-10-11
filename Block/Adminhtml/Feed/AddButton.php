<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Block\Adminhtml\Feed;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Ui\Component\Control\Container;
use CommerceGears\DataFeed\Model\Preset\PresetProvider;

/**
 * "Add Feed" split button: blank feed (main part) or one of the ready templates.
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
                'id' => 'commercegears-datafeed-add-' . $code,
                'label' => __('From template: %1', $label),
                'onclick' => sprintf("setLocation('%s')", $this->urlBuilder->getUrl('*/*/new', ['preset' => $code])),
                'default' => false,
            ];
        }
        $options['blank'] = [
            'id' => 'commercegears-datafeed-add-blank',
            'label' => __('Blank feed'),
            'onclick' => sprintf("setLocation('%s')", $this->urlBuilder->getUrl('*/*/new')),
            // The main part of the button clicks the default option (Magento_Ui split button script).
            'default' => true,
        ];
        return [
            'id' => 'add_new_feed',
            'label' => __('Add Feed'),
            'class' => 'add primary',
            'button_class' => '',
            'class_name' => Container::SPLIT_BUTTON,
            'options' => $options,
            // The split button never attaches on_click to its main part (the handler targets an element that does
            // not exist), so the main part opens the default option above. Empty, so no handler is rendered at all.
            'on_click' => '',
            'sort_order' => 10,
        ];
    }
}
