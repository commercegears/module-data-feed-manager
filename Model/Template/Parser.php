<?php
/**
 * @copyright Copyright (c) CommerceGears (https://www.commercegearscommerce.co.uk/)
 */
declare(strict_types=1);

namespace CommerceGears\DataFeed\Model\Template;

/**
 * Parses the feed template language into an array based syntax tree.
 *
 *   {{ expression }}                       output (escaped for the feed format)
 *   {% for item in expression %}…{% endfor %}
 *   {% if expr %}…{% elseif expr %}…{% else %}…{% endif %}
 *   {% set name = expression %}
 *   {# comment #}
 *
 * Expressions: variables (product.name, product['name']), strings, numbers, true/false/null, [lists],
 * operators (or and not == != < > <= >= in ~ + - * / % ?: ??), filters (value | truncate(100)).
 *
 * Nodes: ['text', string] | ['out', expr, line] | ['for', var, expr, body, line]
 *        | ['if', [[expr, body], ...], elseBody] | ['set', name, expr]
 * Expressions: ['lit', v] | ['var', name] | ['get', expr, expr] | ['filter', name, expr, args, line]
 *        | ['not', expr] | ['neg', expr] | ['bin', op, l, r] | ['cond', c, a, b] | ['default', l, r] | ['arr', list]
 */
class Parser
{
    private const BINARY_POWER = [
        '?' => 5, '??' => 8,
        'or' => 10, '||' => 10,
        'and' => 20, '&&' => 20,
        '==' => 40, '!=' => 40, '<' => 40, '>' => 40, '<=' => 40, '>=' => 40, 'in' => 40,
        '~' => 50,
        '+' => 60, '-' => 60,
        '*' => 70, '/' => 70, '%' => 70,
        '|' => 90,
        '.' => 100, '[' => 100,
    ];

    /** @var array<int, array{type: string, value: string, line: int}> */
    private array $tokens = [];
    private int $pos = 0;

    /** @var array<int, array{0: string, 1: string}> */
    private array $exprTokens = [];
    private int $exprPos = 0;
    private int $exprLine = 1;

    /** @var array<string, true> */
    private array $referencedKeys = [];

    public function __construct(private readonly Filters $filters)
    {
    }

    /**
     * Parses a full XML/JSON template. When $requireItemLoop is set, a top-level
     * "{% for <var> in products %}" loop is mandatory and marks the repeated item part.
     *
     * @throws SyntaxException
     */
    public function parseTemplate(string $source, bool $requireItemLoop = true): CompiledTemplate
    {
        $this->referencedKeys = [];
        $nodes = $this->parseSource($source);

        $header = $nodes;
        $item = [];
        $footer = [];
        $itemVar = 'product';
        $found = false;
        foreach ($nodes as $index => $node) {
            if ($node[0] === 'for' && $node[2] === ['var', 'products']) {
                if ($found) {
                    throw new SyntaxException(
                        __('Line %1: the template may contain only one "for ... in products" loop.', $node[4])
                    );
                }
                $found = true;
                $header = array_slice($nodes, 0, $index);
                $item = $node[3];
                $itemVar = $node[1];
                $footer = array_slice($nodes, $index + 1);
            }
        }
        if ($requireItemLoop && !$found) {
            throw new SyntaxException(
                __('The template needs a top-level loop over the products: {% for product in products %} … {% endfor %}')
            );
        }

        return new CompiledTemplate($header, $item, $footer, $itemVar, array_keys($this->referencedKeys), $found);
    }

    /**
     * Parses a single CSV column value (text with {{ }} and {% %} tags).
     *
     * @return array<int, array>
     * @throws SyntaxException
     */
    public function parseFragment(string $source): array
    {
        return $this->parseSource($source);
    }

    /**
     * @return string[]
     */
    public function getReferencedKeys(): array
    {
        return array_keys($this->referencedKeys);
    }

    public function resetReferencedKeys(): void
    {
        $this->referencedKeys = [];
    }

    /**
     * @return array<int, array>
     * @throws SyntaxException
     */
    private function parseSource(string $source): array
    {
        $this->tokens = $this->tokenize($source);
        $this->pos = 0;
        [$nodes, $end] = $this->parseNodes([]);
        if ($end !== null) {
            throw new SyntaxException(__('Line %1: unexpected "%2".', $end['line'], $end['value']));
        }
        return $nodes;
    }

    /**
     * @return array<int, array{type: string, value: string, line: int}>
     * @throws SyntaxException
     */
    private function tokenize(string $source): array
    {
        $source = str_replace("\r\n", "\n", $source);
        $parts = preg_split(
            '/(\{\{.*?\}\}|\{%.*?%\}|\{#.*?#\})/s',
            $source,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE
        );
        if ($parts === false) {
            throw new SyntaxException(__('The template could not be read (invalid UTF-8?).'));
        }

        $tokens = [];
        foreach ($parts as $i => [$text, $offset]) {
            $line = substr_count($source, "\n", 0, $offset) + 1;
            if ($i % 2 === 0) {
                if (preg_match('/\{\{|\{%/', $text, $m, PREG_OFFSET_CAPTURE)) {
                    $errorLine = $line + substr_count($text, "\n", 0, $m[0][1]);
                    throw new SyntaxException(__('Line %1: "%2" is not closed.', $errorLine, $m[0][0]));
                }
                $tokens[] = ['type' => 'text', 'value' => $text, 'line' => $line];
                continue;
            }
            $type = match (substr($text, 0, 2)) {
                '{{' => 'out',
                '{%' => 'tag',
                default => 'comment',
            };
            $tokens[] = ['type' => $type, 'value' => trim(substr($text, 2, -2)), 'line' => $line];
        }

        return $this->trimStandaloneTags($tokens);
    }

    /**
     * Removes the line of a {% tag %} or {# comment #} that stands alone on its line,
     * so loops and conditions do not leave blank lines in the output.
     *
     * @param array<int, array{type: string, value: string, line: int}> $tokens
     * @return array<int, array{type: string, value: string, line: int}>
     */
    private function trimStandaloneTags(array $tokens): array
    {
        $lineStartText = null;
        $atLineStart = true;
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $type = $tokens[$i]['type'];
            if ($type === 'text') {
                $value = $tokens[$i]['value'];
                if ($value !== '') {
                    $atLineStart = (bool)preg_match('/(^|\n)[ \t]*$/', $value)
                        && ($atLineStart || str_contains($value, "\n"));
                    $lineStartText = $atLineStart ? $i : null;
                }
                continue;
            }
            if ($type === 'out') {
                $atLineStart = false;
                $lineStartText = null;
                continue;
            }
            if (!$atLineStart) {
                continue;
            }
            $next = $i + 1 < $count ? $tokens[$i + 1] : null;
            if ($next === null) {
                if ($lineStartText !== null) {
                    $tokens[$lineStartText]['value'] = rtrim($tokens[$lineStartText]['value'], " \t");
                }
                continue;
            }
            if ($next['type'] === 'text' && preg_match('/^[ \t]*(\n|$)/', $next['value'], $m)) {
                if ($m[1] === '' && $i + 2 < $count) {
                    continue; // another tag follows on the same line
                }
                if ($lineStartText !== null) {
                    $tokens[$lineStartText]['value'] = rtrim($tokens[$lineStartText]['value'], " \t");
                }
                $tokens[$i + 1]['value'] = (string)substr($next['value'], strlen($m[0]));
                $atLineStart = true;
                $lineStartText = null;
            }
        }
        return $tokens;
    }

    /**
     * @param string[] $endTags
     * @return array{0: array<int, array>, 1: array{type: string, value: string, line: int, keyword?: string}|null}
     * @throws SyntaxException
     */
    private function parseNodes(array $endTags): array
    {
        $nodes = [];
        while ($this->pos < count($this->tokens)) {
            $token = $this->tokens[$this->pos++];
            switch ($token['type']) {
                case 'text':
                    if ($token['value'] !== '') {
                        $nodes[] = ['text', $token['value']];
                    }
                    break;
                case 'comment':
                    break;
                case 'out':
                    if ($token['value'] === '') {
                        throw new SyntaxException(__('Line %1: empty {{ }}.', $token['line']));
                    }
                    $nodes[] = ['out', $this->parseExpressionString($token['value'], $token['line']), $token['line']];
                    break;
                case 'tag':
                    $keyword = strtolower((string)preg_replace('/\s.*$/s', '', $token['value']));
                    if (in_array($keyword, $endTags, true)) {
                        $token['keyword'] = $keyword;
                        return [$nodes, $token];
                    }
                    $nodes[] = $this->parseTag($keyword, $token);
                    break;
            }
        }
        if ($endTags) {
            throw new SyntaxException(__('A "{% %1 %}" tag is missing at the end of the template.', $endTags[0]));
        }
        return [$nodes, null];
    }

    /**
     * @param array{type: string, value: string, line: int} $token
     * @return array<int, mixed>
     * @throws SyntaxException
     */
    private function parseTag(string $keyword, array $token): array
    {
        $line = $token['line'];
        $content = $token['value'];
        switch ($keyword) {
            case 'for':
                if (!preg_match('/^for\s+([A-Za-z_]\w*)\s+in\s+(.+)$/s', $content, $m)) {
                    throw new SyntaxException(__('Line %1: use {% for item in list %}.', $line));
                }
                $expr = $this->parseExpressionString($m[2], $line);
                [$body, $end] = $this->parseNodes(['endfor']);
                return ['for', $m[1], $expr, $body, $line];
            case 'if':
                $branches = [];
                $condition = $this->parseExpressionString(substr($content, 2), $line);
                $else = [];
                while (true) {
                    [$body, $end] = $this->parseNodes(['elseif', 'elif', 'else', 'endif']);
                    $branches[] = [$condition, $body];
                    if ($end['keyword'] === 'endif') {
                        break;
                    }
                    if ($end['keyword'] === 'else') {
                        [$else] = $this->parseNodes(['endif']);
                        break;
                    }
                    $condition = $this->parseExpressionString(
                        (string)preg_replace('/^\w+/', '', $end['value']),
                        $end['line']
                    );
                }
                return ['if', $branches, $else];
            case 'set':
                if (!preg_match('/^set\s+([A-Za-z_]\w*)\s*=\s*(.+)$/s', $content, $m)) {
                    throw new SyntaxException(__('Line %1: use {% set name = value %}.', $line));
                }
                return ['set', $m[1], $this->parseExpressionString($m[2], $line)];
            case 'endfor':
            case 'endif':
            case 'else':
            case 'elseif':
            case 'elif':
                throw new SyntaxException(__('Line %1: "%2" without a matching opening tag.', $line, $keyword));
            default:
                throw new SyntaxException(__('Line %1: unknown tag "%2".', $line, $keyword));
        }
    }

    /**
     * Parses a standalone expression (used for templates and for validating values).
     *
     * @return array<int, mixed>
     * @throws SyntaxException
     */
    public function parseExpressionString(string $expression, int $line = 1): array
    {
        $this->exprTokens = $this->tokenizeExpression($expression, $line);
        $this->exprPos = 0;
        $this->exprLine = $line;
        if ($this->peek()[0] === 'eof') {
            throw new SyntaxException(__('Line %1: an expression is missing.', $line));
        }
        $expr = $this->parseExpression(0);
        $rest = $this->peek();
        if ($rest[0] !== 'eof') {
            throw new SyntaxException(__('Line %1: unexpected "%2" in "%3".', $line, $rest[1], trim($expression)));
        }
        return $expr;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     * @throws SyntaxException
     */
    private function tokenizeExpression(string $expression, int $line): array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($expression);
        $pattern = '/\s*(?:(\d+(?:\.\d+)?)|(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")|([A-Za-z_][A-Za-z0-9_]*)'
            . '|(==|!=|<=|>=|&&|\|\||\?\?|[<>+\-*\/%~|.,()\[\]:?!]))/A';
        while ($offset < $length) {
            if (!preg_match($pattern, $expression, $m, 0, $offset)) {
                if (trim(substr($expression, $offset)) === '') {
                    break;
                }
                throw new SyntaxException(
                    __('Line %1: cannot read "%2".', $line, trim(substr($expression, $offset, 20)))
                );
            }
            $offset += strlen($m[0]);
            if (isset($m[1]) && $m[1] !== '') {
                $tokens[] = ['num', $m[1]];
            } elseif (isset($m[2]) && $m[2] !== '') {
                $tokens[] = ['str', $this->unescapeString($m[2])];
            } elseif (isset($m[3]) && $m[3] !== '') {
                $tokens[] = ['name', $m[3]];
            } elseif (isset($m[4]) && $m[4] !== '') {
                $tokens[] = ['op', $m[4]];
            }
        }
        $tokens[] = ['eof', ''];
        return $tokens;
    }

    private function unescapeString(string $quoted): string
    {
        $inner = substr($quoted, 1, -1);
        return (string)preg_replace_callback(
            '/\\\\(.)/s',
            static fn (array $m): string => match ($m[1]) {
                'n' => "\n",
                't' => "\t",
                'r' => "\r",
                default => $m[1],
            },
            $inner
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function peek(): array
    {
        return $this->exprTokens[$this->exprPos];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function next(): array
    {
        $token = $this->exprTokens[$this->exprPos];
        if ($token[0] !== 'eof') {
            $this->exprPos++;
        }
        return $token;
    }

    /**
     * @throws SyntaxException
     */
    private function expectOp(string $op): void
    {
        $token = $this->next();
        if ($token[0] !== 'op' || $token[1] !== $op) {
            throw new SyntaxException(
                __('Line %1: expected "%2" but found "%3".', $this->exprLine, $op, $token[1] === '' ? 'end' : $token[1])
            );
        }
    }

    /**
     * @param array{0: string, 1: string} $token
     */
    private function bindingPower(array $token): int
    {
        if ($token[0] === 'op' || ($token[0] === 'name' && in_array($token[1], ['and', 'or', 'in'], true))) {
            return self::BINARY_POWER[$token[1]] ?? 0;
        }
        return 0;
    }

    /**
     * @return array<int, mixed>
     * @throws SyntaxException
     */
    private function parseExpression(int $rightBindingPower): array
    {
        $left = $this->nud($this->next());
        while ($rightBindingPower < $this->bindingPower($this->peek())) {
            $left = $this->led($this->next(), $left);
        }
        return $left;
    }

    /**
     * @param array{0: string, 1: string} $token
     * @return array<int, mixed>
     * @throws SyntaxException
     */
    private function nud(array $token): array
    {
        [$type, $value] = $token;
        switch ($type) {
            case 'num':
                return ['lit', str_contains($value, '.') ? (float)$value : (int)$value];
            case 'str':
                return ['lit', $value];
            case 'name':
                $lower = strtolower($value);
                if ($lower === 'true' || $lower === 'false') {
                    return ['lit', $lower === 'true'];
                }
                if ($lower === 'null' || $lower === 'none') {
                    return ['lit', null];
                }
                if ($lower === 'not') {
                    return ['not', $this->parseExpression(25)];
                }
                return ['var', $value];
            case 'op':
                if ($value === '(') {
                    $expr = $this->parseExpression(0);
                    $this->expectOp(')');
                    return $expr;
                }
                if ($value === '-') {
                    return ['neg', $this->parseExpression(80)];
                }
                if ($value === '!') {
                    return ['not', $this->parseExpression(25)];
                }
                if ($value === '[') {
                    $items = [];
                    if (!($this->peek()[0] === 'op' && $this->peek()[1] === ']')) {
                        do {
                            $items[] = $this->parseExpression(0);
                            $more = $this->peek()[0] === 'op' && $this->peek()[1] === ',';
                            if ($more) {
                                $this->next();
                            }
                        } while ($more);
                    }
                    $this->expectOp(']');
                    return ['arr', $items];
                }
                break;
        }
        throw new SyntaxException(
            __('Line %1: unexpected "%2".', $this->exprLine, $value === '' ? 'end of expression' : $value)
        );
    }

    /**
     * @param array{0: string, 1: string} $token
     * @param array<int, mixed> $left
     * @return array<int, mixed>
     * @throws SyntaxException
     */
    private function led(array $token, array $left): array
    {
        $op = $token[1];
        switch ($op) {
            case '.':
                $name = $this->next();
                if ($name[0] !== 'name' && $name[0] !== 'num') {
                    throw new SyntaxException(__('Line %1: a property name must follow ".".', $this->exprLine));
                }
                $this->referencedKeys[$name[1]] = true;
                return ['get', $left, ['lit', $name[1]]];
            case '[':
                $key = $this->parseExpression(0);
                $this->expectOp(']');
                if ($key[0] === 'lit' && is_string($key[1])) {
                    $this->referencedKeys[$key[1]] = true;
                }
                return ['get', $left, $key];
            case '|':
                $name = $this->next();
                if ($name[0] !== 'name') {
                    throw new SyntaxException(__('Line %1: a filter name must follow "|".', $this->exprLine));
                }
                $filter = strtolower($name[1]);
                if (!$this->filters->has($filter)) {
                    throw new SyntaxException(__('Line %1: unknown filter "%2".', $this->exprLine, $name[1]));
                }
                $args = [];
                if ($this->peek()[0] === 'op' && $this->peek()[1] === '(') {
                    $this->next();
                    if (!($this->peek()[0] === 'op' && $this->peek()[1] === ')')) {
                        do {
                            $args[] = $this->parseExpression(0);
                            $more = $this->peek()[0] === 'op' && $this->peek()[1] === ',';
                            if ($more) {
                                $this->next();
                            }
                        } while ($more);
                    }
                    $this->expectOp(')');
                }
                return ['filter', $filter, $left, $args, $this->exprLine];
            case '?':
                $then = $this->parseExpression(0);
                $this->expectOp(':');
                return ['cond', $left, $then, $this->parseExpression(4)];
            case '??':
                return ['default', $left, $this->parseExpression(7)];
            default:
                $power = self::BINARY_POWER[$op] ?? 0;
                return ['bin', strtolower($op), $left, $this->parseExpression($power)];
        }
    }
}
