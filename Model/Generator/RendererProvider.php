<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator;

use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Model\Generator\Renderer\DelimitedRendererFactory;
use Softaware\DataFeed\Model\Generator\Renderer\RendererInterface;
use Softaware\DataFeed\Model\Generator\Renderer\TemplateRendererFactory;

class RendererProvider
{
    public function __construct(
        private readonly TemplateRendererFactory $templateRendererFactory,
        private readonly DelimitedRendererFactory $delimitedRendererFactory
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function get(string $format): RendererInterface
    {
        return match ($format) {
            FeedInterface::FORMAT_XML, FeedInterface::FORMAT_JSON => $this->templateRendererFactory->create(),
            FeedInterface::FORMAT_CSV, FeedInterface::FORMAT_TXT => $this->delimitedRendererFactory->create(),
            default => throw new LocalizedException(__('Unknown feed format "%1".', $format)),
        };
    }
}
