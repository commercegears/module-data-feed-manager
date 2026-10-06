<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator\Renderer;

use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Model\Generator\ActiveContentGuard;
use Softaware\DataFeed\Model\Product\ProductContext;
use Softaware\DataFeed\Model\Product\StoreContext;
use Softaware\DataFeed\Model\Template\CompiledTemplate;
use Softaware\DataFeed\Model\Template\Parser;
use Softaware\DataFeed\Model\Template\Runtime;
use Softaware\DataFeed\Model\Template\RuntimeFactory;

/**
 * XML and JSON feeds from a template with a "for product in products" loop.
 * Every item is checked on its own, so one broken product cannot break the whole file.
 */
class TemplateRenderer implements RendererInterface
{
    private ?CompiledTemplate $template = null;
    private ?Runtime $runtime = null;
    private ?StoreContext $store = null;
    private string $format = FeedInterface::FORMAT_XML;
    private ?string $namespaceDeclarations = null;

    public function __construct(
        private readonly Parser $parser,
        private readonly RuntimeFactory $runtimeFactory,
        private readonly ActiveContentGuard $activeContentGuard
    ) {
    }

    public function compile(FeedInterface $feed): void
    {
        $this->format = $feed->getFormat();
        if (trim($feed->getTemplate()) === '') {
            throw new LocalizedException(__('The feed template is empty.'));
        }
        $this->template = $this->parser->parseTemplate($feed->getTemplate());
        $this->runtime = $this->runtimeFactory->create();
        $this->runtime->setEscape($this->format === FeedInterface::FORMAT_JSON ? Runtime::ESCAPE_JSON : Runtime::ESCAPE_XML);
        $this->namespaceDeclarations = null;
    }

    public function getReferencedKeys(): array
    {
        return $this->template ? $this->template->referencedKeys : [];
    }

    public function setStoreContext(StoreContext $store): void
    {
        $this->store = $store;
        $this->runtime?->setCurrency($store->currency);
    }

    public function renderHeader(): string
    {
        $scope = $this->globalScope();
        return $this->runtime->render($this->template->header, $scope);
    }

    public function renderItem(ProductContext $product): string
    {
        $scope = $this->globalScope();
        $scope[$this->template->itemVariable] = $product;
        $output = $this->runtime->render($this->template->item, $scope);
        if (trim($output) === '') {
            return '';
        }
        $this->validate($output);
        return $output;
    }

    public function getSeparator(): string
    {
        return $this->format === FeedInterface::FORMAT_JSON ? ',' : '';
    }

    public function renderFooter(): string
    {
        $scope = $this->globalScope();
        return $this->runtime->render($this->template->footer, $scope);
    }

    /**
     * @return array<string, mixed>
     */
    private function globalScope(): array
    {
        return [
            'store' => $this->store?->storeVariables ?? [],
            'feed' => $this->store?->feedVariables ?? [],
            'currency' => $this->store?->currency ?? '',
            'now' => $this->store?->feedVariables['generated_at'] ?? date(DATE_ATOM),
        ];
    }

    private function validate(string $output): void
    {
        if ($this->format === FeedInterface::FORMAT_JSON) {
            $trimmed = rtrim(trim($output), ',');
            if (json_decode('[' . $trimmed . ']') === null && json_decode('{' . $trimmed . '}') === null) {
                throw new \RuntimeException('invalid JSON: ' . json_last_error_msg());
            }
            return;
        }
        if ($this->namespaceDeclarations === null) {
            preg_match_all('/\sxmlns(?::[A-Za-z_][\w.-]*)?\s*=\s*(?:"[^"]*"|\'[^\']*\')/', $this->renderHeader(), $m);
            $this->namespaceDeclarations = implode('', array_unique($m[0]));
        }
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $ok = $document->loadXML('<sw_root' . $this->namespaceDeclarations . '>' . $output . '</sw_root>', LIBXML_NONET);
        $error = libxml_get_last_error();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new \RuntimeException('invalid XML: ' . trim($error ? $error->message : 'unknown error'));
        }
        $unsafe = $this->activeContentGuard->checkDocument($document, true);
        if ($unsafe !== null) {
            throw new \RuntimeException($unsafe);
        }
    }
}
