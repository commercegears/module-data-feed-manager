<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Generator;

use Magento\Framework\Exception\LocalizedException;
use CommerceGears\DataFeed\Api\Data\FeedInterface;
use CommerceGears\DataFeed\Model\Generator\Renderer\DelimitedRenderer;
use CommerceGears\DataFeed\Model\Product\ProductProvider;

/**
 * Renders the first items of a feed (saved or unsaved) without writing a file.
 */
class Preview
{
    private const MAX_ERRORS = 20;

    /** Most items a preview renders, whatever the setting says. */
    public const MAX_ITEMS = 20;

    /** Products read at most while looking for items (templates that print nothing for most products). */
    private const MAX_PRODUCTS = 500;

    /** Seconds after which the preview stops reading products. */
    private const TIME_BUDGET = 10.0;

    public function __construct(
        private readonly Generator $generator,
        private readonly ProductProvider $productProvider,
        private readonly OutputValidator $outputValidator
    ) {
    }

    /**
     * @return array{output: string, items: int, total: int, configurable: int, mode: string, last_items: int|null,
     *     errors: string[], valid: bool, validation: string|null, format: string, delimiter: string, truncated: bool}
     * @throws LocalizedException
     */
    public function render(FeedInterface $feed, int $limit): array
    {
        $limit = max(1, min(self::MAX_ITEMS, $limit));
        $started = microtime(true);
        $session = $this->generator->createSession($feed);
        $renderer = $session->renderer;
        $total = $this->productProvider->count($feed, $session->store);
        // The match count is of catalogue products; in the variant modes the feed has one item per variant.
        $configurable = $feed->getParentChildMode() === FeedInterface::MODE_PARENT
            ? 0
            : $this->productProvider->countConfigurable($feed, $session->store);

        $output = $renderer->renderHeader();
        $items = 0;
        $errors = [];
        $lastId = 0;
        $read = 0;
        $truncated = false;
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
            $read += $batch['processed'];
            // Stop early when items keep failing, so a broken template does not walk the whole catalogue.
            if ($batch['processed'] < $limit || count($errors) >= self::MAX_ERRORS) {
                break;
            }
            if ($items < $limit && ($read >= self::MAX_PRODUCTS || microtime(true) - $started > self::TIME_BUDGET)) {
                $truncated = true;
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
            'configurable' => $configurable,
            'mode' => $feed->getParentChildMode(),
            'last_items' => $this->lastItems($feed),
            'errors' => $errors,
            'valid' => $validation === null,
            'validation' => $validation,
            'format' => $feed->getFormat(),
            'delimiter' => $feed->isDelimited() ? $delimiter : '',
            'truncated' => $truncated,
        ];
    }

    /**
     * Items in the last successfully generated file of a saved feed, or null.
     */
    private function lastItems(FeedInterface $feed): ?int
    {
        if (!$feed->getId() || $feed->getData(FeedInterface::GENERATED_ITEMS) === null
            || (string)$feed->getData(FeedInterface::GENERATED_STATUS) === 'error'
        ) {
            return null;
        }
        return (int)$feed->getData(FeedInterface::GENERATED_ITEMS);
    }
}
