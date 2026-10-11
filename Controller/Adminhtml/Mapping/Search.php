<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use CommerceGears\DataFeed\Model\ResourceModel\Taxonomy;

/**
 * Autocomplete for Google product categories (read only; the admin sends jQuery requests as POST).
 */
class Search extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'CommerceGears_DataFeed::mapping';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly Taxonomy $taxonomy
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $term = mb_substr(trim((string)$this->getRequest()->getParam('term')), 0, 100);
        $items = [];
        if (mb_strlen($term) >= 2) {
            foreach ($this->taxonomy->search($term, 25) as $id => $path) {
                $items[] = ['value' => $id . ' - ' . $path, 'label' => $id . ' - ' . $path];
            }
        }
        return $this->jsonFactory->create()->setData($items);
    }
}
