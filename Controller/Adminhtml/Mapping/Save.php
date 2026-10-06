<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Softaware\DataFeed\Model\ResourceModel\CategoryMap;
use Softaware\DataFeed\Model\ResourceModel\Taxonomy;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_DataFeed::mapping';

    public function __construct(
        Action\Context $context,
        private readonly CategoryMap $categoryMap,
        private readonly Taxonomy $taxonomy
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $posted = $this->getRequest()->getParam('mapping');
        $map = [];
        $unknown = [];
        if (is_array($posted)) {
            foreach ($posted as $categoryId => $value) {
                $value = trim((string)$value);
                if ($value === '') {
                    $map[(int)$categoryId] = null;
                } elseif (preg_match('/^(\d+)/', $value, $m)) {
                    $map[(int)$categoryId] = (int)$m[1];
                } else {
                    $unknown[] = $value;
                }
            }
        }
        $ids = array_values(array_unique(array_filter($map)));
        $known = $this->taxonomy->getPaths($ids);
        foreach ($map as $categoryId => $taxonomyId) {
            if ($taxonomyId !== null && !isset($known[$taxonomyId])) {
                $unknown[] = (string)$taxonomyId;
                unset($map[$categoryId]);
            }
        }
        try {
            $this->categoryMap->saveMap($map);
            $this->messageManager->addSuccessMessage(__('The category mapping has been saved.'));
            if ($unknown) {
                $this->messageManager->addWarningMessage(
                    __('Not saved (choose a suggestion from the list): %1', implode(', ', array_unique($unknown)))
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('The mapping could not be saved.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
