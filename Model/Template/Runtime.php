<?php
/**
 * @copyright Copyright (c) Softaware Commerce (https://www.softawarecommerce.co.uk/)
 */
declare(strict_types=1);

namespace Softaware\DataFeed\Model\Template;

/**
 * Evaluates parsed template nodes. Sandboxed: templates can only read variables
 * and apply the filters in {@see Filters}; there is no access to PHP functions or objects.
 */
class Runtime
{
    public const ESCAPE_XML = 'xml';
    public const ESCAPE_JSON = 'json';
    public const ESCAPE_NONE = 'none';

    private const MAX_LOOP_ITEMS = 10000;

    /** Longest value (bytes) an expression may produce, so a template cannot exhaust memory. */
    public const MAX_VALUE_LENGTH = 1048576;

    /** Longest output (bytes) of one render call: an item, the header or the footer. */
    public const MAX_OUTPUT_LENGTH = 8388608;

    /** Loop iterations allowed in one render call (nested loops multiply). */
    private const MAX_ITERATIONS = 1000000;

    private string $escape = self::ESCAPE_NONE;
    private string $currency = '';
    private int $depth = 0;
    private int $iterations = 0;

    public function __construct(private readonly Filters $filters)
    {
    }

    public function setEscape(string $escape): self
    {
        $this->escape = $escape;
        return $this;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @param array<int, array> $nodes
     * @param array<string, mixed> $scope
     */
    public function render(array $nodes, array &$scope): string
    {
        if ($this->depth === 0) {
            $this->iterations = 0;
        }
        $this->depth++;
        try {
            return $this->renderNodes($nodes, $scope);
        } finally {
            $this->depth--;
        }
    }

    /**
     * @param array<int, array> $nodes
     * @param array<string, mixed> $scope
     */
    private function renderNodes(array $nodes, array &$scope): string
    {
        $output = '';
        foreach ($nodes as $node) {
            switch ($node[0]) {
                case 'text':
                    $output .= $node[1];
                    break;
                case 'out':
                    $value = $this->evaluate($node[1], $scope);
                    $output .= $value instanceof SafeString ? (string)$value : $this->escape($this->stringify($value));
                    break;
                case 'for':
                    $output .= $this->renderLoop($node, $scope);
                    break;
                case 'if':
                    foreach ($node[1] as [$condition, $body]) {
                        if (self::isTruthy($this->evaluate($condition, $scope))) {
                            $output .= $this->render($body, $scope);
                            continue 3;
                        }
                    }
                    $output .= $this->render($node[2], $scope);
                    break;
                case 'set':
                    $scope[$node[1]] = $this->evaluate($node[2], $scope);
                    break;
            }
            if (strlen($output) > self::MAX_OUTPUT_LENGTH) {
                throw new \LengthException(sprintf(
                    'the output is longer than %d MB; check loops and repeated text',
                    self::MAX_OUTPUT_LENGTH >> 20
                ));
            }
        }
        return $output;
    }

    /**
     * Renders nodes without escaping and returns the raw value when the nodes are a single output
     * (used for CSV columns, so numbers and lists keep their type until written).
     *
     * @param array<int, array> $nodes
     * @param array<string, mixed> $scope
     */
    public function renderValue(array $nodes, array &$scope): string
    {
        $previous = $this->escape;
        $this->escape = self::ESCAPE_NONE;
        try {
            return $this->render($nodes, $scope);
        } finally {
            $this->escape = $previous;
        }
    }

    /**
     * @param array<int, mixed> $node
     * @param array<string, mixed> $scope
     */
    private function renderLoop(array $node, array &$scope): string
    {
        [, $variable, $expr, $body] = $node;
        $items = $this->evaluate($expr, $scope);
        if (!is_array($items) || $items === []) {
            return '';
        }
        $items = array_slice(array_values($items), 0, self::MAX_LOOP_ITEMS);
        $hadVar = array_key_exists($variable, $scope);
        $oldVar = $scope[$variable] ?? null;
        $hadLoop = array_key_exists('loop', $scope);
        $oldLoop = $scope['loop'] ?? null;
        $length = count($items);
        $output = '';
        foreach ($items as $index => $item) {
            if (++$this->iterations > self::MAX_ITERATIONS) {
                throw new \LengthException(sprintf(
                    'Line %d: more than %d loop steps for one item; check nested loops',
                    (int)($node[4] ?? 0),
                    self::MAX_ITERATIONS
                ));
            }
            $scope[$variable] = $item;
            $scope['loop'] = [
                'index' => $index + 1,
                'index0' => $index,
                'first' => $index === 0,
                'last' => $index === $length - 1,
                'length' => $length,
            ];
            $output .= $this->render($body, $scope);
        }
        if ($hadVar) {
            $scope[$variable] = $oldVar;
        } else {
            unset($scope[$variable]);
        }
        if ($hadLoop) {
            $scope['loop'] = $oldLoop;
        } else {
            unset($scope['loop']);
        }
        return $output;
    }

    /**
     * @param array<int, mixed> $expr
     * @param array<string, mixed> $scope
     */
    public function evaluate(array $expr, array $scope): mixed
    {
        switch ($expr[0]) {
            case 'lit':
                return $expr[1];
            case 'var':
                return $scope[$expr[1]] ?? null;
            case 'get':
                return $this->getAttribute($this->evaluate($expr[1], $scope), $this->evaluate($expr[2], $scope));
            case 'filter':
                $args = [];
                foreach ($expr[3] as $arg) {
                    $args[] = $this->evaluate($arg, $scope);
                }
                try {
                    $value = $this->filters->apply($expr[1], $this->evaluate($expr[2], $scope), $args, $this);
                    self::assertLength($value);
                } catch (\InvalidArgumentException | \LengthException $e) {
                    $message = $e->getMessage();
                    if (!str_starts_with($message, 'Line ') && isset($expr[4])) {
                        $message = sprintf('Line %d: %s', (int)$expr[4], $message);
                    }
                    throw new \InvalidArgumentException($message, 0, $e);
                }
                return $value;
            case 'not':
                return !self::isTruthy($this->evaluate($expr[1], $scope));
            case 'neg':
                $value = $this->evaluate($expr[1], $scope);
                return is_numeric($value) ? -$value : null;
            case 'cond':
                return self::isTruthy($this->evaluate($expr[1], $scope))
                    ? $this->evaluate($expr[2], $scope)
                    : $this->evaluate($expr[3], $scope);
            case 'default':
                $value = $this->evaluate($expr[1], $scope);
                return self::isEmpty($value) ? $this->evaluate($expr[2], $scope) : $value;
            case 'arr':
                return array_map(fn ($item) => $this->evaluate($item, $scope), $expr[1]);
            case 'bin':
                return $this->binary($expr[1], $expr[2], $expr[3], $scope);
        }
        return null;
    }

    /**
     * @param array<int, mixed> $leftExpr
     * @param array<int, mixed> $rightExpr
     * @param array<string, mixed> $scope
     */
    private function binary(string $op, array $leftExpr, array $rightExpr, array $scope): mixed
    {
        $left = $this->evaluate($leftExpr, $scope);
        if ($op === 'and' || $op === '&&') {
            return self::isTruthy($left) && self::isTruthy($this->evaluate($rightExpr, $scope));
        }
        if ($op === 'or' || $op === '||') {
            return self::isTruthy($left) || self::isTruthy($this->evaluate($rightExpr, $scope));
        }
        $right = $this->evaluate($rightExpr, $scope);
        if ($left instanceof SafeString) {
            $left = (string)$left;
        }
        if ($right instanceof SafeString) {
            $right = (string)$right;
        }
        switch ($op) {
            case '~':
                $left = $this->stringify($left);
                $right = $this->stringify($right);
                if (strlen($left) + strlen($right) > self::MAX_VALUE_LENGTH) {
                    throw new \LengthException(self::tooLongMessage());
                }
                return $left . $right;
            case 'in':
                if (is_array($right)) {
                    $needle = $this->stringify($left);
                    foreach ($right as $candidate) {
                        if ($this->stringify($candidate) === $needle) {
                            return true;
                        }
                    }
                    return false;
                }
                $haystack = $this->stringify($right);
                $needle = $this->stringify($left);
                return $needle !== '' && str_contains($haystack, $needle);
            case '==':
            case '!=':
                $equal = $this->compare($left, $right) === 0;
                return $op === '==' ? $equal : !$equal;
            case '<':
                return $this->compare($left, $right) < 0;
            case '>':
                return $this->compare($left, $right) > 0;
            case '<=':
                return $this->compare($left, $right) <= 0;
            case '>=':
                return $this->compare($left, $right) >= 0;
        }
        if (!is_numeric($left) || !is_numeric($right)) {
            return null;
        }
        $left = (float)$left;
        $right = (float)$right;
        return match ($op) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right == 0.0 ? null : $left / $right,
            '%' => $right == 0.0 ? null : fmod($left, $right),
            default => null,
        };
    }

    private function compare(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float)$left <=> (float)$right;
        }
        if (($left === null || $left === '') && is_numeric($right)) {
            return $right == 0 ? 0 : -1;
        }
        if (is_bool($left) || is_bool($right)) {
            return self::isTruthy($left) <=> self::isTruthy($right);
        }
        return strcmp($this->stringify($left), $this->stringify($right));
    }

    private function getAttribute(mixed $object, mixed $key): mixed
    {
        if ($key === null || is_array($key) || is_object($key)) {
            return null;
        }
        if ($object instanceof VariableProviderInterface) {
            return $object->get((string)$key);
        }
        if (is_array($object)) {
            return $object[$key] ?? null;
        }
        return null;
    }

    public function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value)) {
            return (string)$value;
        }
        if (is_float($value)) {
            return self::formatFloat($value);
        }
        if ($value instanceof \Stringable) {
            return (string)$value;
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item) || $item instanceof \Stringable) {
                    $text = $this->stringify($item);
                    if ($text !== '') {
                        $parts[] = $text;
                    }
                }
            }
            return implode(', ', $parts);
        }
        return '';
    }

    private function escape(string $value): string
    {
        return match ($this->escape) {
            self::ESCAPE_XML => self::escapeXml($value),
            self::ESCAPE_JSON => self::escapeJson($value),
            default => $value,
        };
    }

    public static function formatFloat(float $value): string
    {
        if (!is_finite($value)) {
            return '';
        }
        $text = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
        return $text === '-0' ? '0' : $text;
    }

    public static function cleanXmlChars(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');
        return (string)preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
    }

    public static function escapeXml(string $value): string
    {
        return htmlspecialchars(self::cleanXmlChars($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function escapeJson(string $value): string
    {
        $json = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        return $json === false ? '' : substr($json, 1, -1);
    }

    /**
     * @throws \LengthException when a string value is longer than {@see MAX_VALUE_LENGTH}
     */
    public static function assertLength(mixed $value): void
    {
        if ((is_string($value) || $value instanceof SafeString) && strlen((string)$value) > self::MAX_VALUE_LENGTH) {
            throw new \LengthException(self::tooLongMessage());
        }
    }

    public static function tooLongMessage(): string
    {
        return sprintf('a value is longer than %d MB', self::MAX_VALUE_LENGTH >> 20);
    }

    public static function isEmpty(mixed $value): bool
    {
        if ($value instanceof SafeString) {
            $value = (string)$value;
        }
        return $value === null || $value === '' || $value === [] || $value === false;
    }

    public static function isTruthy(mixed $value): bool
    {
        if ($value instanceof SafeString) {
            $value = (string)$value;
        }
        if (is_string($value)) {
            return $value !== '' && $value !== '0';
        }
        if (is_object($value)) {
            return true;
        }
        return (bool)$value;
    }
}
