<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator;

use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Model\Generator\Renderer\DelimitedRenderer;
use Softaware\DataFeed\Model\Product\ProductProvider;

/**
 * Renders the first items of a feed (saved or unsaved) without writing a file.
 */
class Preview
{
    private const MAX_ERRORS = 20;

    public function __construct(
        private readonly Generator $generator,
        private readonly ProductProvider $productProvider,
        private readonly OutputValidator $outputValidator
    ) {
    }

    /**
     * @return array{output: string, items: int, total: int, errors: string[], valid: bool, validation: string|null}
     * @throws LocalizedException
     */
    public function render(FeedInterface $feed, int $limit): array
    {
        $session = $this->generator->createSession($feed);
        $renderer = $session->renderer;
        $total = $this->productProvider->count($feed, $session->store);

        $output = $renderer->renderHeader();
        $items = 0;
        $errors = [];
        $lastId = 0;
        // Children modes can produce several items per product; read on until enough items are rendered.
        while ($items < $limit) {
            $batch = $this->productProvider->getBatch($feed, $session->store, $session->needs, $lastId, $limit);
            foreach ($batch['items'] as $product) {
                if ($items >= $limit) {
                    break;
                }
                try {
                    $text = $renderer->renderItem($product);
                } catch (\Throwable $e) {
                    $errors[] = sprintf('SKU %s: %s', $product->get('sku'), $e->getMessage());
                    continue;
                }
                if ($text === '') {
                    continue;
                }
                $output .= ($items > 0 ? $renderer->getSeparator() : '') . $text;
                $items++;
            }
            // Stop early when items keep failing, so a broken template does not walk the whole catalogue.
            if ($batch['processed'] < $limit || count($errors) >= self::MAX_ERRORS) {
                break;
            }
            $lastId = $batch['last_id'];
        }
        $output .= $renderer->renderFooter();

        $delimiter = DelimitedRenderer::resolveDelimiter(
            (string)$feed->getData(FeedInterface::CSV_DELIMITER),
            $feed->getFormat()
        );
        $enclosure = (string)$feed->getData(FeedInterface::CSV_ENCLOSURE);
        $encoding = (string)$feed->getData(FeedInterface::CSV_ENCODING);
        $display = $output;
        if ($feed->isDelimited() && !in_array($encoding, ['', 'UTF-8', 'UTF-8-BOM'], true)) {
            $display = (string)mb_convert_encoding($output, 'UTF-8', $encoding);
        }
        $validation = $this->outputValidator->validateString(
            $display,
            $feed->getFormat(),
            $delimiter,
            $enclosure === 'none' ? '' : ($enclosure ?: '"')
        );

        return [
            'output' => (string)preg_replace('/^\xEF\xBB\xBF/', '', $display),
            'items' => $items,
            'total' => $total,
            'errors' => $errors,
            'valid' => $validation === null,
            'validation' => $validation,
        ];
    }
}
