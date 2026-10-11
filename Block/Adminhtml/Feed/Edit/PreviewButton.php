<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Opens the preview of the feed as entered in the form (unsaved changes included); nothing is saved.
 */
class PreviewButton extends GenericButton implements ButtonProviderInterface
{
    private const TARGET = 'commercegears_datafeed_feed_form.areas.content_section.content_section.preview_bar';

    public function getButtonData(): array
    {
        return [
            'label' => __('Preview'),
            'class' => 'action-secondary',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            ['targetName' => self::TARGET, 'actionName' => 'openModal'],
                        ],
                    ],
                ],
            ],
            'on_click' => '',
            'sort_order' => 35,
        ];
    }
}
