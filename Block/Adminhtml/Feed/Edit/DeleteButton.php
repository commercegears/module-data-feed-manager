<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Block\Adminhtml\Feed\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        if (!$this->getFeedId() || !$this->authorization->isAllowed('Softaware_DataFeed::feed_delete')) {
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
