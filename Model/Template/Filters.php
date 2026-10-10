<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Template;

/**
 * Filters available in feed templates: {{ value | name(arg1, arg2) }}.
 */
class Filters
{
    /**
     * Filter name => short description (shown in the admin reference).
     */
    public const DESCRIPTIONS = [
        'strip_tags' => 'Remove HTML tags and decode entities',
        'truncate' => 'truncate(length, suffix = "…"): shorten to a maximum length, at a word boundary where possible',
        'upper' => 'Upper case',
        'lower' => 'Lower case',
        'capitalize' => 'First letter upper case',
        'title' => 'Each word capitalised',
        'trim' => 'Remove surrounding white space',
        'clean' => 'Collapse line breaks and repeated spaces into single spaces',
        'number_format' => 'number_format(decimals = 2, decimal_point = ".", thousands = ""): format a number',
        'round' => 'round(precision = 0)',
        'price' => 'price(currency = feed currency): "12.50 GBP"',
        'default' => 'default(fallback): use the fallback when the value is empty',
        'if' => 'if(then, else = ""): output "then" when the value is truthy, otherwise "else"',
        'replace' => 'replace(search, replacement): replace text',
        'regex_replace' => 'regex_replace(pattern, replacement): replace using a regular expression',
        'prepend' => 'prepend(text): add text before a non-empty value',
        'append' => 'append(text): add text after a non-empty value',
        'join' => 'join(separator = ", "): join a list',
        'split' => 'split(separator = ","): turn text into a list',
        'first' => 'First element of a list',
        'last' => 'Last element of a list',
        'slice' => 'slice(start, length): part of a list or text',
        'count' => 'Number of elements in a list (or characters in text)',
        'unique' => 'Remove duplicate list elements',
        'date' => 'date(format = "Y-m-d"): format a date',
        'html_decode' => 'Decode HTML entities',
        'url_encode' => 'URL-encode',
        'json' => 'Output as a JSON value (number, string, list), not escaped again',
        'cdata' => 'Wrap in <![CDATA[ ]]> (XML only), not escaped again',
        'raw' => 'Output without escaping',
        'escape' => 'escape(type = "xml"): escape for xml, html, json or url',
    ];

    private const ALIASES = [
        'length' => 'count',
        'nl2space' => 'clean',
        'e' => 'escape',
        'ucfirst' => 'capitalize',
        'abs' => 'abs',
    ];

    public function has(string $name): bool
    {
        return isset(self::DESCRIPTIONS[$name]) || isset(self::ALIASES[$name]);
    }

    /**
     * @param array<int, mixed> $args
     */
    public function apply(string $name, mixed $value, array $args, Runtime $runtime): mixed
    {
        $name = self::ALIASES[$name] ?? $name;
        if ($value instanceof SafeString && !in_array($name, ['raw', 'default', 'if', 'json'], true)) {
            $value = (string)$value;
        }
        switch ($name) {
            case 'strip_tags':
                $text = (string)preg_replace('#<\s*(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>#i', ' $0', $this->str($value));
                $text = strip_tags($text);
                return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            case 'truncate':
                return $this->truncate($this->str($value), (int)($args[0] ?? 100), (string)($args[1] ?? '…'));
            case 'upper':
                return mb_strtoupper($this->str($value), 'UTF-8');
            case 'lower':
                return mb_strtolower($this->str($value), 'UTF-8');
            case 'capitalize':
                $text = $this->str($value);
                return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($text, 1, null, 'UTF-8');
            case 'title':
                return mb_convert_case($this->str($value), MB_CASE_TITLE, 'UTF-8');
            case 'trim':
                return trim($this->str($value));
            case 'clean':
                return trim((string)preg_replace('/\s+/u', ' ', $this->str($value)));
            case 'number_format':
                if (!is_numeric($value)) {
                    return $value === null || $value === '' ? '' : $value;
                }
                return number_format(
                    (float)$value,
                    (int)($args[0] ?? 2),
                    (string)($args[1] ?? '.'),
                    (string)($args[2] ?? '')
                );
            case 'round':
                return is_numeric($value) ? round((float)$value, (int)($args[0] ?? 0)) : $value;
            case 'abs':
                return is_numeric($value) ? abs((float)$value) : $value;
            case 'price':
                if (!is_numeric($value)) {
                    return '';
                }
                $currency = (string)($args[0] ?? $runtime->getCurrency());
                return trim(number_format((float)$value, 2, '.', '') . ' ' . $currency);
            case 'default':
                return Runtime::isEmpty($value) ? ($args[0] ?? '') : $value;
            case 'if':
                return Runtime::isTruthy($value) ? ($args[0] ?? '1') : ($args[1] ?? '');
            case 'replace':
                $text = $this->str($value);
                if (is_array($args[0] ?? null)) {
                    $pairs = array_map([$this, 'str'], $args[0]);
                    // Upper bound of the result: every occurrence of a key replaced by its value.
                    $estimate = strlen($text);
                    foreach ($pairs as $search => $replacement) {
                        $search = (string)$search;
                        if ($search !== '' && strlen($replacement) > strlen($search)) {
                            $estimate += substr_count($text, $search) * (strlen($replacement) - strlen($search));
                        }
                    }
                    $this->assertResultLength($estimate);
                    return strtr($text, $pairs);
                }
                $search = $this->str($args[0] ?? '');
                $replacement = $this->str($args[1] ?? '');
                if ($search !== '' && $replacement !== '') {
                    $this->assertResultLength(strlen($text) + substr_count($text, $search) * strlen($replacement));
                }
                return str_replace($search, $replacement, $text);
            case 'regex_replace':
                $pattern = (string)($args[0] ?? '');
                if ($pattern === '' || @preg_match($pattern, '') === false) {
                    throw new \InvalidArgumentException(sprintf('regex_replace: invalid pattern "%s"', $pattern));
                }
                $text = $this->str($value);
                $replacement = $this->str($args[1] ?? '');
                // Matches do not overlap, so every back reference adds at most the length of the text.
                $matches = (int)@preg_match_all($pattern, $text);
                $references = preg_match_all('/\$\d+|\$\{\d+\}|\\\\\d+/', $replacement);
                $this->assertResultLength(
                    strlen($text) + $matches * strlen($replacement) + (int)$references * strlen($text)
                );
                return (string)preg_replace($pattern, $replacement, $text);
            case 'prepend':
                $text = $this->str($value);
                return $text === '' ? '' : ($args[0] ?? '') . $text;
            case 'append':
                $text = $this->str($value);
                return $text === '' ? '' : $text . ($args[0] ?? '');
            case 'join':
                $parts = array_map([$runtime, 'stringify'], $this->toList($value));
                $separator = $this->str($args[0] ?? ', ');
                $this->assertResultLength(
                    array_sum(array_map('strlen', $parts)) + max(0, count($parts) - 1) * strlen($separator)
                );
                return implode($separator, $parts);
            case 'split':
                $text = $this->str($value);
                $separator = (string)($args[0] ?? ',');
                return $text === '' || $separator === '' ? [] : array_map('trim', explode($separator, $text));
            case 'first':
                if (is_array($value)) {
                    return $value ? reset($value) : null;
                }
                return mb_substr($this->str($value), 0, 1, 'UTF-8');
            case 'last':
                if (is_array($value)) {
                    return $value ? end($value) : null;
                }
                return mb_substr($this->str($value), -1, 1, 'UTF-8');
            case 'slice':
                $start = (int)($args[0] ?? 0);
                $length = isset($args[1]) ? (int)$args[1] : null;
                if (is_array($value)) {
                    return array_slice(array_values($value), $start, $length);
                }
                return mb_substr($this->str($value), $start, $length, 'UTF-8');
            case 'count':
                if (is_array($value)) {
                    return count($value);
                }
                return mb_strlen($this->str($value), 'UTF-8');
            case 'unique':
                return array_values(array_unique(array_map([$runtime, 'stringify'], $this->toList($value))));
            case 'date':
                return $this->formatDate($value, (string)($args[0] ?? 'Y-m-d'));
            case 'html_decode':
                return html_entity_decode($this->str($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            case 'url_encode':
                return rawurlencode($this->str($value));
            case 'json':
                if ($value instanceof SafeString) {
                    $value = (string)$value;
                }
                $json = json_encode(
                    $this->toJsonValue($value, $runtime),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                        | JSON_PRESERVE_ZERO_FRACTION
                );
                return new SafeString($json === false ? 'null' : $json);
            case 'cdata':
                $text = Runtime::cleanXmlChars($this->str($value));
                return new SafeString('<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $text) . ']]>');
            case 'raw':
                return new SafeString($runtime->stringify($value));
            case 'escape':
                $type = strtolower((string)($args[0] ?? 'xml'));
                $text = $this->str($value);
                return new SafeString(match ($type) {
                    'html' => htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'json' => Runtime::escapeJson($text),
                    'url' => rawurlencode($text),
                    default => Runtime::escapeXml($text),
                });
        }
        throw new \InvalidArgumentException(sprintf('Unknown filter "%s"', $name));
    }

    /**
     * Refuses a result before it is built when it would be longer than {@see Runtime::MAX_VALUE_LENGTH}.
     */
    private function assertResultLength(int $length): void
    {
        if ($length > Runtime::MAX_VALUE_LENGTH) {
            throw new \LengthException(Runtime::tooLongMessage());
        }
    }

    private function str(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            return implode(', ', array_filter(array_map(
                static fn ($v) => is_scalar($v) || $v instanceof \Stringable ? (string)$v : '',
                $value
            ), static fn ($v) => $v !== ''));
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            return Runtime::formatFloat($value);
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string)$value;
        }
        return '';
    }

    /**
     * @return array<int|string, mixed>
     */
    private function toList(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        return $value === null || $value === '' ? [] : [$value];
    }

    private function truncate(string $text, int $length, string $suffix): string
    {
        if ($length <= 0 || mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        $suffixLength = mb_strlen($suffix, 'UTF-8');
        $cut = mb_substr($text, 0, max(0, $length - $suffixLength), 'UTF-8');
        $space = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($space !== false && $space > (int)($length * 0.7)) {
            $cut = mb_substr($cut, 0, $space, 'UTF-8');
        }
        return rtrim($cut, " \t\n\r,.;:-") . $suffix;
    }

    private function formatDate(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            if ($value instanceof \DateTimeInterface) {
                $date = $value;
            } elseif (is_numeric($value)) {
                $date = (new \DateTimeImmutable())->setTimestamp((int)$value);
            } else {
                $date = new \DateTimeImmutable((string)$value);
            }
        } catch (\Exception) {
            return (string)$value;
        }
        return $date->format($format);
    }

    private function toJsonValue(mixed $value, Runtime $runtime): mixed
    {
        if ($value instanceof VariableProviderInterface) {
            return $value->get('sku');
        }
        if ($value instanceof \Stringable) {
            return (string)$value;
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->toJsonValue($v, $runtime), $value);
        }
        if (is_string($value)) {
            return mb_scrub($value, 'UTF-8');
        }
        return $value;
    }
}
