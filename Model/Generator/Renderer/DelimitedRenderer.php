<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator\Renderer;

use Magento\Framework\Exception\LocalizedException;
use Softaware\DataFeed\Api\Data\FeedInterface;
use Softaware\DataFeed\Model\Product\ProductContext;
use Softaware\DataFeed\Model\Product\StoreContext;
use Softaware\DataFeed\Model\Template\Parser;
use Softaware\DataFeed\Model\Template\Runtime;
use Softaware\DataFeed\Model\Template\RuntimeFactory;
use Softaware\DataFeed\Model\Template\SyntaxException;

/**
 * CSV and delimited TXT feeds from a column map (header, value template, default).
 */
class DelimitedRenderer implements RendererInterface
{
    public const ENCODINGS = [
        'UTF-8' => 'UTF-8',
        'UTF-8-BOM' => 'UTF-8 with BOM',
        'ISO-8859-1' => 'ISO-8859-1 (Latin-1)',
        'Windows-1252' => 'Windows-1252',
    ];

    /** @var array<int, array{header: string, value: array, default: array}> */
    private array $columns = [];
    private ?Runtime $runtime = null;
    private ?StoreContext $store = null;
    private string $delimiter = ',';
    private string $enclosure = '"';
    private string $encoding = 'UTF-8';
    private string $eol = "\n";
    private bool $includeHeader = true;
    /** @var string[] */
    private array $referencedKeys = [];

    public function __construct(
        private readonly Parser $parser,
        private readonly RuntimeFactory $runtimeFactory
    ) {
    }

    public function compile(FeedInterface $feed): void
    {
        $columns = $feed->getColumns();
        if (!$columns) {
            throw new LocalizedException(__('Add at least one column.'));
        }
        $this->parser->resetReferencedKeys();
        $this->columns = [];
        foreach ($columns as $index => $column) {
            try {
                $this->columns[] = [
                    'header' => $column['header'],
                    'value' => $this->parser->parseFragment($column['value']),
                    'default' => $this->parser->parseFragment($column['default']),
                ];
            } catch (SyntaxException $e) {
                throw new SyntaxException(
                    __('Column %1 (%2): %3', $index + 1, $column['header'], $e->getMessage())
                );
            }
        }
        $this->referencedKeys = $this->parser->getReferencedKeys();
        $this->delimiter = self::resolveDelimiter((string)$feed->getData(FeedInterface::CSV_DELIMITER), $feed->getFormat());
        $enclosure = (string)$feed->getData(FeedInterface::CSV_ENCLOSURE);
        $this->enclosure = $enclosure === 'none' ? '' : ($enclosure === '' ? '"' : substr($enclosure, 0, 1));
        $this->encoding = (string)($feed->getData(FeedInterface::CSV_ENCODING) ?: 'UTF-8');
        if (!isset(self::ENCODINGS[$this->encoding])) {
            $this->encoding = 'UTF-8';
        }
        $this->eol = $feed->getData(FeedInterface::CSV_LINE_ENDING) === 'crlf' ? "\r\n" : "\n";
        $header = $feed->getData(FeedInterface::CSV_INCLUDE_HEADER);
        $this->includeHeader = $header === null || (bool)$header;
        $this->runtime = $this->runtimeFactory->create();
        $this->runtime->setEscape(Runtime::ESCAPE_NONE);
    }

    public static function resolveDelimiter(string $value, string $format): string
    {
        return match ($value) {
            'tab', '\t', "\t" => "\t",
            'comma' => ',',
            'semicolon' => ';',
            'pipe' => '|',
            '' => $format === FeedInterface::FORMAT_TXT ? "\t" : ',',
            default => substr($value, 0, 1),
        };
    }

    public function getReferencedKeys(): array
    {
        return $this->referencedKeys;
    }

    public function setStoreContext(StoreContext $store): void
    {
        $this->store = $store;
        $this->runtime?->setCurrency($store->currency);
    }

    public function renderHeader(): string
    {
        $bom = $this->encoding === 'UTF-8-BOM' ? "\xEF\xBB\xBF" : '';
        if (!$this->includeHeader) {
            return $bom;
        }
        return $bom . $this->line(array_column($this->columns, 'header'));
    }

    public function renderItem(ProductContext $product): string
    {
        $scope = [
            'product' => $product,
            'store' => $this->store?->storeVariables ?? [],
            'feed' => $this->store?->feedVariables ?? [],
            'currency' => $this->store?->currency ?? '',
            'now' => $this->store?->feedVariables['generated_at'] ?? date(DATE_ATOM),
        ];
        $values = [];
        foreach ($this->columns as $column) {
            $value = $this->runtime->renderValue($column['value'], $scope);
            if (trim($value) === '' && $column['default']) {
                $value = $this->runtime->renderValue($column['default'], $scope);
            }
            $values[] = $value;
        }
        return $this->line($values);
    }

    public function getSeparator(): string
    {
        return '';
    }

    public function renderFooter(): string
    {
        return '';
    }

    /**
     * @param string[] $values
     */
    private function line(array $values): string
    {
        if ($this->enclosure === '') {
            $clean = array_map(
                fn (string $v) => trim((string)preg_replace('/[\r\n\t]+/', ' ', str_replace($this->delimiter, ' ', $v))),
                $values
            );
            $line = implode($this->delimiter, $clean) . $this->eol;
        } else {
            $stream = fopen('php://temp', 'r+');
            fputcsv($stream, $values, $this->delimiter, $this->enclosure, '', $this->eol);
            rewind($stream);
            $line = (string)stream_get_contents($stream);
            fclose($stream);
        }
        return $this->convert($line);
    }

    private function convert(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');
        if ($this->encoding === 'UTF-8' || $this->encoding === 'UTF-8-BOM') {
            return $text;
        }
        return (string)mb_convert_encoding($text, $this->encoding, 'UTF-8');
    }
}
