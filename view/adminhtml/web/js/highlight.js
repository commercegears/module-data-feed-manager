/**
 * Small, dependency-free syntax highlighter for feed templates and feed output.
 *
 * Modes: "xml-template", "json-template" (template tags over XML / JSON), "xml", "json", "csv" (output).
 * tokens() returns [className|null, text] pairs; toHtml() and toLines() escape the text, so the result can be
 * assigned to innerHTML safely.
 */
define([], function () {
    'use strict';

    var KEYWORDS = {
            'for': 1, 'endfor': 1, 'in': 1, 'if': 1, 'elseif': 1, 'else': 1, 'endif': 1, 'set': 1,
            'and': 1, 'or': 1, 'not': 1, 'is': 1
        },
        CONSTANTS = {'true': 1, 'false': 1, 'null': 1, 'none': 1},
        EXPRESSION = /\s+|"(?:[^"\\]|\\.)*"?|'(?:[^'\\]|\\.)*'?|\d+(?:\.\d+)?|[A-Za-z_][A-Za-z0-9_]*|\?\?|==|!=|<=|>=|&&|\|\||[\s\S]/g;

    function escapeHtml(text) {
        return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /**
     * Expression inside {{ }} or {% %}.
     */
    function expressionTokens(source, isTag, out) {
        var match, text, previous = '', first = isTag, cls;

        EXPRESSION.lastIndex = 0;
        while ((match = EXPRESSION.exec(source)) !== null) {
            text = match[0];
            cls = 'tpl-op';
            if (/^\s+$/.test(text)) {
                out.push([null, text]);
                continue;
            }
            if (text.charAt(0) === '"' || text.charAt(0) === '\'') {
                cls = 'tpl-string';
            } else if (/^\d/.test(text)) {
                cls = 'tpl-number';
            } else if (/^[A-Za-z_]/.test(text)) {
                if (previous === '|') {
                    cls = 'tpl-filter';
                } else if (previous === '.') {
                    cls = 'tpl-prop';
                } else if (first || KEYWORDS.hasOwnProperty(text)) {
                    cls = 'tpl-keyword';
                } else if (CONSTANTS.hasOwnProperty(text.toLowerCase())) {
                    cls = 'tpl-number';
                } else {
                    cls = 'tpl-var';
                }
            }
            first = false;
            previous = text;
            out.push([cls, text]);
        }
    }

    /**
     * Stateful XML lexer: the state survives template tags inside tags and attribute values.
     */
    function XmlLexer() {
        this.state = 'text';
        this.quote = '';
    }

    XmlLexer.prototype.push = function (text, out) {
        var pos = 0, length = text.length, match, end, close,
            tagPattern = /(\s+)|(\/?>)|([^\s=\/>"']+)|(=)|(["'])|([\s\S])/y,
            openPattern = /<\/?([A-Za-z_][\w.:-]*)?/y;

        while (pos < length) {
            switch (this.state) {
                case 'text':
                    end = text.indexOf('<', pos);
                    if (end === -1) {
                        this.text(text.slice(pos), out);
                        return;
                    }
                    if (end > pos) {
                        this.text(text.slice(pos, end), out);
                        pos = end;
                    }
                    if (text.startsWith('<!--', pos)) {
                        this.state = 'comment';
                        out.push(['xml-comment', '<!--']);
                        pos += 4;
                    } else if (text.startsWith('<![CDATA[', pos)) {
                        this.state = 'cdata';
                        out.push(['xml-cdata', '<![CDATA[']);
                        pos += 9;
                    } else if (text.startsWith('<?', pos) || text.startsWith('<!', pos)) {
                        this.state = 'decl';
                        out.push(['xml-decl', text.substr(pos, 2)]);
                        pos += 2;
                    } else {
                        openPattern.lastIndex = pos;
                        match = openPattern.exec(text);
                        out.push(['xml-punct', match[0].charAt(1) === '/' ? '</' : '<']);
                        if (match[1]) {
                            out.push(['xml-tag', match[1]]);
                        }
                        pos += match[0].length;
                        this.state = 'tag';
                    }
                    break;
                case 'tag':
                    tagPattern.lastIndex = pos;
                    match = tagPattern.exec(text);
                    if (match[1]) {
                        out.push([null, match[1]]);
                    } else if (match[2]) {
                        out.push(['xml-punct', match[2]]);
                        this.state = 'text';
                    } else if (match[3]) {
                        out.push(['xml-attr', match[3]]);
                    } else if (match[4]) {
                        out.push(['xml-punct', '=']);
                    } else if (match[5]) {
                        out.push(['xml-string', match[5]]);
                        this.quote = match[5];
                        this.state = 'value';
                    } else {
                        out.push(['xml-punct', match[6]]);
                    }
                    pos += match[0].length;
                    break;
                case 'value':
                    end = text.indexOf(this.quote, pos);
                    if (end === -1) {
                        out.push(['xml-string', text.slice(pos)]);
                        return;
                    }
                    out.push(['xml-string', text.slice(pos, end + 1)]);
                    pos = end + 1;
                    this.state = 'tag';
                    break;
                default:
                    // comment, cdata, decl
                    close = {comment: '-->', cdata: ']]>', decl: '>'}[this.state];
                    end = text.indexOf(close, pos);
                    if (end === -1) {
                        out.push(['xml-' + this.state, text.slice(pos)]);
                        return;
                    }
                    out.push(['xml-' + this.state, text.slice(pos, end + close.length)]);
                    pos = end + close.length;
                    this.state = 'text';
            }
        }
    };

    XmlLexer.prototype.text = function (text, out) {
        var parts = text.split(/(&[#\w]+;)/), i;

        for (i = 0; i < parts.length; i++) {
            if (parts[i] !== '') {
                out.push([i % 2 ? 'xml-entity' : null, parts[i]]);
            }
        }
    };

    /**
     * JSON lexer; strings may be interrupted by template tags ("title": "{{ product.name }}").
     */
    function JsonLexer() {
        this.inString = false;
    }

    JsonLexer.prototype.push = function (text, out) {
        var pattern = /"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null|[{}\[\],:]|\s+|[^"\s{}\[\],:]+/g,
            match, end, pos = 0;

        while (pos < text.length) {
            if (this.inString) {
                end = pos;
                while (end < text.length && text.charAt(end) !== '"') {
                    end += text.charAt(end) === '\\' ? 2 : 1;
                }
                if (end >= text.length) {
                    out.push(['json-string', text.slice(pos)]);
                    return;
                }
                out.push(['json-string', text.slice(pos, end + 1)]);
                pos = end + 1;
                this.inString = false;
                continue;
            }
            pattern.lastIndex = pos;
            match = pattern.exec(text);
            if (!match || match.index !== pos) {
                out.push([null, text.slice(pos)]);
                return;
            }
            if (match[0] === '"') {
                out.push(['json-string', '"']);
                this.inString = true;
            } else if (/^[{}\[\],:]$/.test(match[0])) {
                out.push(['json-punct', match[0]]);
            } else if (/^\s+$/.test(match[0])) {
                out.push([null, match[0]]);
            } else if (/^(true|false|null|-?\d)/.test(match[0])) {
                out.push(['json-number', match[0]]);
            } else {
                out.push([null, match[0]]);
            }
            pos += match[0].length;
        }
    };

    /**
     * Marks JSON object keys: a string followed (after white space) by a colon.
     */
    function markJsonKeys(tokens) {
        var i, j;

        for (i = 0; i < tokens.length; i++) {
            if (tokens[i][0] === 'json-punct' && tokens[i][1] === ':') {
                for (j = i - 1; j >= 0 && tokens[j][0] === null && /^\s*$/.test(tokens[j][1]); j--) {
                    // skip white space
                }
                while (j >= 0 && tokens[j][0] === 'json-string') {
                    tokens[j][0] = 'json-key';
                    if (tokens[j][1].charAt(0) === '"' && (j === 0 || tokens[j - 1][0] !== 'json-string')) {
                        break;
                    }
                    j--;
                }
            }
        }
        return tokens;
    }

    function templateTokens(source, base) {
        var out = [], lexer = base === 'json' ? new JsonLexer() : new XmlLexer(),
            pos = 0, start, end, open, close, cls;

        var opener = /\{[{%#]/g;

        while (pos < source.length) {
            opener.lastIndex = pos;
            start = opener.exec(source);
            if (start === null) {
                lexer.push(source.slice(pos), out);
                break;
            }
            start = start.index;
            if (start > pos) {
                lexer.push(source.slice(pos, start), out);
            }
            open = source.substr(start, 2);
            close = open === '{{' ? '}}' : (open === '{%' ? '%}' : '#}');
            end = source.indexOf(close, start + 2);
            if (end === -1) {
                // Not closed: the template parser refuses this, so show it as an error.
                out.push(['tpl-error', open]);
                pos = start + 2;
                continue;
            }
            if (open === '{#') {
                out.push(['tpl-comment', source.slice(start, end + 2)]);
            } else {
                cls = open === '{%' ? 'tpl-tag' : 'tpl-delim';
                out.push([cls, open]);
                expressionTokens(source.slice(start + 2, end), open === '{%', out);
                out.push([cls, close]);
            }
            pos = end + 2;
        }
        return base === 'json' ? markJsonKeys(out) : out;
    }

    function csvTokens(source, delimiter) {
        var out = [], lines = source.split('\n'), i, line, pattern, match, header;

        delimiter = delimiter || ',';
        pattern = new RegExp('"(?:[^"]|"")*"?|' + escapeRegExp(delimiter) + '|[^"' + escapeRegExp(delimiter) + ']+', 'g');
        for (i = 0; i < lines.length; i++) {
            line = lines[i];
            header = i === 0;
            pattern.lastIndex = 0;
            while ((match = pattern.exec(line)) !== null) {
                if (match[0] === '') {
                    pattern.lastIndex++;
                    continue;
                }
                if (match[0] === delimiter) {
                    out.push(['csv-delim', delimiter === '\t' ? '\t' : delimiter]);
                } else {
                    out.push([header ? 'csv-header' : (match[0].charAt(0) === '"' ? 'csv-string' : null), match[0]]);
                }
            }
            if (i < lines.length - 1) {
                out.push([null, '\n']);
            }
        }
        return out;
    }

    function escapeRegExp(text) {
        return text.replace(/[.*+?^${}()|[\]\\\/-]/g, '\\$&');
    }

    function tokens(source, mode, options) {
        source = String(source || '');
        switch (mode) {
            case 'xml-template':
                return templateTokens(source, 'xml');
            case 'json-template':
                return templateTokens(source, 'json');
            case 'xml':
                return (function () {
                    var out = [];

                    new XmlLexer().push(source, out);
                    return out;
                })();
            case 'json':
                return (function () {
                    var out = [];

                    new JsonLexer().push(source, out);
                    return markJsonKeys(out);
                })();
            case 'csv':
                return csvTokens(source, options && options.delimiter);
        }
        return [[null, source]];
    }

    function span(cls, text) {
        var html = escapeHtml(text);

        return cls ? '<span class="sw-hl-' + cls + '">' + html + '</span>' : html;
    }

    return {
        tokens: tokens,
        escapeHtml: escapeHtml,

        /**
         * Highlighted HTML (escaped).
         */
        toHtml: function (source, mode, options) {
            var list = tokens(source, mode, options), html = '', i;

            for (i = 0; i < list.length; i++) {
                html += span(list[i][0], list[i][1]);
            }
            return html;
        },

        /**
         * Highlighted HTML with every line wrapped in <span class="sw-hl-line"> (for line numbers and markers).
         */
        toLines: function (source, mode, options) {
            var list = tokens(source, mode, options), html = '<span class="sw-hl-line">', i, j, parts;

            for (i = 0; i < list.length; i++) {
                parts = list[i][1].split('\n');
                for (j = 0; j < parts.length; j++) {
                    if (j > 0) {
                        html += '</span>\n<span class="sw-hl-line">';
                    }
                    if (parts[j] !== '') {
                        html += span(list[i][0], parts[j]);
                    }
                }
            }
            return html + '</span>';
        }
    };
});
