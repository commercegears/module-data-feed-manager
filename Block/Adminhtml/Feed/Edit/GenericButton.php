<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Block\Adminhtml\Feed\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\AuthorizationInterface;

abstract class GenericButton
{
    public function __construct(
        protected readonly Context $context,
        protected readonly AuthorizationInterface $authorization
    ) {
    }

    protected function getFeedId(): int
    {
        return (int)$this->context->getRequest()->getParam('id');
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function getUrl(string $route, array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
