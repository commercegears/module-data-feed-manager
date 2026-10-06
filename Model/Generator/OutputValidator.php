<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Generator;

use Softaware\DataFeed\Api\Data\FeedInterface;

/**
 * Checks a complete feed file (streamed for XML, so large files are fine).
 */
class OutputValidator
{
    /** JSON files above this size are not decoded as a whole; every item was already checked. */
    private const JSON_FULL_CHECK_LIMIT = 64 * 1024 * 1024;

    /**
     * @return string|null error message, or null when valid
     */
    public function validateFile(string $path, string $format): ?string
    {
        return match ($format) {
            FeedInterface::FORMAT_XML => $this->validateXmlFile($path),
            FeedInterface::FORMAT_JSON => filesize($path) <= self::JSON_FULL_CHECK_LIMIT
                ? $this->validateJson((string)file_get_contents($path))
                : null,
            default => null,
        };
    }

    public function validateString(string $content, string $format, string $delimiter = ',', string $enclosure = '"'): ?string
    {
        switch ($format) {
            case FeedInterface::FORMAT_XML:
                $previous = libxml_use_internal_errors(true);
                $document = new \DOMDocument();
                $ok = $document->loadXML($content, LIBXML_NONET);
                $error = libxml_get_last_error();
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                return $ok ? null : (string)__('XML error on line %1: %2', $error ? $error->line : 0, trim($error ? $error->message : ''));
            case FeedInterface::FORMAT_JSON:
                return $this->validateJson($content);
            default:
                return $this->validateDelimited($content, $delimiter, $enclosure);
        }
    }

    private function validateXmlFile(string $path): ?string
    {
        $previous = libxml_use_internal_errors(true);
        $reader = new \XMLReader();
        if (!$reader->open($path, null, LIBXML_NONET)) {
            libxml_use_internal_errors($previous);
            return (string)__('The XML file could not be opened.');
        }
        $message = null;
        while (@$reader->read()) {
            // Streaming parse: only well-formedness is checked.
        }
        $error = libxml_get_last_error();
        if ($error) {
            $message = (string)__('XML error on line %1: %2', $error->line, trim($error->message));
        }
        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $message;
    }

    private function validateJson(string $content): ?string
    {
        $content = (string)preg_replace('/^\xEF\xBB\xBF/', '', $content);
        json_decode($content, false, 512);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return (string)__('JSON error: %1', json_last_error_msg());
        }
        return null;
    }

    private function validateDelimited(string $content, string $delimiter, string $enclosure): ?string
    {
        $content = (string)preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $columns = null;
        $line = 0;
        $message = null;
        while (($row = fgetcsv($stream, null, $delimiter, $enclosure === '' ? "\0" : $enclosure, '')) !== false) {
            $line++;
            if ($row === [null]) {
                continue;
            }
            if ($columns === null) {
                $columns = count($row);
            } elseif (count($row) !== $columns) {
                $message = (string)__('Row %1 has %2 columns, the first row has %3.', $line, count($row), $columns);
                break;
            }
        }
        fclose($stream);
        return $message;
    }
}
