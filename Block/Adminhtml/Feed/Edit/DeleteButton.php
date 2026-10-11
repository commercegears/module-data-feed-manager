<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        if (!$this->getFeedId() || !$this->authorization->isAllowed('CommerceGears_DataFeed::feed_delete')) {
            return [];
        }
        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                __('Delete this feed? The generated file stays on the server.'),
                $this->getUrl('*/*/delete', ['id' => $this->getFeedId()])
            ),
            'sort_order' => 20,
        ];
    }
}
