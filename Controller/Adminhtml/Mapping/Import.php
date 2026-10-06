<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Model\Taxonomy\Importer;

/**
 * Imports the Google taxonomy from an uploaded file, or the bundled en-GB list.
 */
class Import extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Softaware_DataFeed::mapping';

    public function __construct(
        Action\Context $context,
        private readonly Importer $importer
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        try {
            $file = $this->getRequest()->getFiles('taxonomy_file');
            if (is_array($file) && !empty($file['tmp_name']) && (int)($file['error'] ?? 1) === UPLOAD_ERR_OK
                && is_uploaded_file($file['tmp_name'])
            ) {
                if ((int)$file['size'] > 10 * 1024 * 1024) {
                    throw new LocalizedException(__('The taxonomy file is larger than 10 MB.'));
                }
                $count = $this->importer->importContent((string)file_get_contents($file['tmp_name']));
            } else {
                $count = $this->importer->importFile();
            }
            $this->messageManager->addSuccessMessage(__('%1 Google product categories imported.', $count));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
