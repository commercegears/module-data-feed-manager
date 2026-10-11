<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Generates the saved version of the feed (use "Save & Generate Now" for unsaved changes).
 */
class GenerateButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        if (!$this->getFeedId() || !$this->authorization->isAllowed('CommerceGears_DataFeed::feed_generate')) {
            return [];
        }
        return [
            'label' => __('Generate Now'),
            'class' => 'action-secondary',
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('*/*/generate', ['id' => $this->getFeedId()])),
            'sort_order' => 40,
        ];
    }
}
