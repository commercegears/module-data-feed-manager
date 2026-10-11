<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Controller\Adminhtml\Feed;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;
use CommerceGears\DataFeed\Model\Preset\PresetProvider;

class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_DataFeed::feeds';

    public function __construct(
        Action\Context $context,
        private readonly PresetProvider $presetProvider
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('CommerceGears_DataFeed::feeds');
        $presets = $this->presetProvider->getOptions();
        $preset = (string)$this->getRequest()->getParam('preset');
        $page->getConfig()->getTitle()->prepend(
            isset($presets[$preset]) ? __('New Feed: %1', $presets[$preset]) : __('New Feed')
        );
        return $page;
    }
}
