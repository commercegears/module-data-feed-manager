<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Ui\Component\Control\Container;

/**
 * Save split button: Save & Continue (default), Save & Close, Save & Generate.
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    private const FORM = 'softaware_datafeed_feed_form.areas';

    public function getButtonData(): array
    {
        $options = [
            [
                'id_hard' => 'save_and_close',
                'label' => __('Save &amp; Close'),
                'data_attribute' => $this->saveAction(['back' => 'close']),
            ],
        ];
        if ($this->authorization->isAllowed('Softaware_DataFeed::feed_generate')) {
            $options[] = [
                'id_hard' => 'save_and_generate',
                'label' => __('Save &amp; Generate Now'),
                'data_attribute' => $this->saveAction(['back' => 'generate']),
            ];
        }
        return [
            'label' => __('Save'),
            // Empty on_click: without it Magento adds a link to the current route (location.href) that races the save.
            'on_click' => '',
            'class' => 'save primary',
            'data_attribute' => $this->saveAction(['back' => 'continue']),
            'class_name' => Container::SPLIT_BUTTON,
            'options' => $options,
            'sort_order' => 90,
        ];
    }

    /**
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function saveAction(array $params): array
    {
        return [
            'mage-init' => [
                'buttonAdapter' => [
                    'actions' => [
                        [
                            'targetName' => self::FORM,
                            'actionName' => 'save',
                            'params' => [true, $params],
                        ],
                    ],
                ],
            ],
        ];
    }
}
