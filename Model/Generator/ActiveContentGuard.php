<?php
/**
 * @copyright Copyright (c) CommerceGears (https://commercegears.com/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Generator;

/**
 * Keeps XML feeds from becoming web pages. Feed files are served from the store's own domain, and browsers run
 * scripts in XML files that use the XHTML or SVG namespaces (or an XSLT stylesheet). Feeds never need these, so
 * such content is refused: in single items (the item is skipped) and in the complete file (it is not published).
 */
class ActiveContentGuard
{
    public const BLOCKED_NAMESPACES = [
        'http://www.w3.org/1999/xhtml' => 'XHTML',
        'http://www.w3.org/2000/svg' => 'SVG',
        'http://www.w3.org/1998/Math/MathML' => 'MathML',
        'http://www.w3.org/1999/XSL/Transform' => 'XSLT',
        'http://www.w3.org/1999/xlink' => 'XLink',
    ];

    private const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';

    /**
     * @return string|null error message, or null when the document is safe
     */
    public function checkDocument(\DOMDocument $document, bool $skipRoot = false): ?string
    {
        $xpath = new \DOMXPath($document);
        // $skipRoot: the root element is a wrapper added for checking a fragment (it carries the feed's namespaces).
        $scope = $skipRoot ? '/*//' : '//';
        foreach (self::BLOCKED_NAMESPACES as $uri => $label) {
            $query = sprintf('%2$s*[namespace-uri()="%1$s"] | %2$s@*[namespace-uri()="%1$s"]', $uri, $scope);
            $nodes = $xpath->query($query);
            if ($nodes !== false && $nodes->length > 0) {
                return $this->namespaceMessage($label, $nodes->item(0)?->nodeName ?? '');
            }
        }
        $stylesheets = $xpath->query('//processing-instruction("xml-stylesheet")');
        if ($stylesheets !== false && $stylesheets->length > 0) {
            return $this->stylesheetMessage();
        }
        return null;
    }

    /**
     * Checks the node the reader is positioned on (streamed validation of complete files).
     *
     * @return string|null error message, or null when the node is safe
     */
    public function checkReaderNode(\XMLReader $reader): ?string
    {
        if ($reader->nodeType === \XMLReader::PI) {
            return strtolower($reader->name) === 'xml-stylesheet' ? $this->stylesheetMessage() : null;
        }
        if ($reader->nodeType !== \XMLReader::ELEMENT) {
            return null;
        }
        if (isset(self::BLOCKED_NAMESPACES[$reader->namespaceURI])) {
            return $this->namespaceMessage(self::BLOCKED_NAMESPACES[$reader->namespaceURI], $reader->name);
        }
        if (!$reader->hasAttributes) {
            return null;
        }
        $message = null;
        while ($message === null && $reader->moveToNextAttribute()) {
            $uri = (string)$reader->namespaceURI;
            if ($uri !== self::XMLNS_NAMESPACE && isset(self::BLOCKED_NAMESPACES[$uri])) {
                $message = $this->namespaceMessage(self::BLOCKED_NAMESPACES[$uri], $reader->name);
            }
        }
        $reader->moveToElement();
        return $message;
    }

    private function namespaceMessage(string $label, string $node): string
    {
        return (string)__(
            '%1 content ("%2") is not allowed in a feed: browsers would run it as a web page on your domain.',
            $label,
            $node
        );
    }

    private function stylesheetMessage(): string
    {
        return (string)__('An xml-stylesheet instruction is not allowed in a feed: browsers would run it as a web page on your domain.');
    }
}
