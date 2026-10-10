/**
 * Feed template field with syntax highlighting and line numbers.
 *
 * The <textarea> stays the real field (value, form post, undo, selection, screen readers); a highlighted <pre>
 * is drawn underneath it and kept in sync on input and scroll. No external libraries.
 */
define([
    'jquery',
    'Magento_Ui/js/form/element/textarea',
    'Softaware_DataFeed/js/highlight'
], function ($, Textarea, highlighter) {
    'use strict';

    var INDENT = '    ',
        LARGE = 200000,
        HUGE = 2000000;

    return Textarea.extend({
        defaults: {
            elementTmpl: 'Softaware_DataFeed/form/element/code-editor',
            valueUpdate: 'input',
            format: 'xml',
            cursorText: '',
            tabMovesFocus: false,
            plainMode: false,
            imports: {
                format: '${ $.provider }:${ $.parentScope }.format'
            },
            listens: {
                format: 'refresh',
                value: 'onValueChange'
            }
        },

        initObservable: function () {
            this._super().observe(['cursorText', 'tabMovesFocus', 'plainMode']);

            return this;
        },

        /**
         * Called by the afterRender binding of the editor wrapper.
         */
        initEditor: function (element) {
            var self = this,
                $root = $(element);

            this.root = element;
            this.input = $root.find('textarea')[0];
            this.code = $root.find('.softaware-datafeed-editor-highlight code')[0];
            this.highlightBox = $root.find('.softaware-datafeed-editor-highlight')[0];
            this.gutter = $root.find('.softaware-datafeed-editor-gutter')[0];
            this.lineCount = 0;

            $(this.input)
                .on('input', function () {
                    self.schedule();
                })
                .on('scroll', function () {
                    self.syncScroll();
                })
                .on('keydown', function (event) {
                    self.onKeyDown(event);
                })
                .on('keyup click focus select', function () {
                    self.updateCursor();
                })
                .on('blur', function () {
                    self.tabMovesFocus(false);
                    self.cursorText('');
                });

            if (window.ResizeObserver) {
                new window.ResizeObserver(function () {
                    self.layout();
                }).observe(this.input);
            }
            // The value binding of the textarea is applied after this wrapper's afterRender.
            window.setTimeout(function () {
                self.refresh();
            }, 0);
        },

        onValueChange: function () {
            // Programmatic changes (preset loaded, form reset) do not fire "input".
            if (this.input && this.input.value !== this.lastRendered) {
                this.schedule();
            }
        },

        mode: function () {
            return this.format === 'json' ? 'json-template' : 'xml-template';
        },

        schedule: function () {
            var self = this,
                length = this.input ? this.input.value.length : 0;

            if (!this.input) {
                return;
            }
            window.clearTimeout(this.timer);
            if (this.frame) {
                window.cancelAnimationFrame(this.frame);
            }
            if (length > LARGE) {
                this.timer = window.setTimeout(function () {
                    self.refresh();
                }, 250);
            } else {
                this.frame = window.requestAnimationFrame(function () {
                    self.frame = null;
                    self.refresh();
                });
            }
        },

        /**
         * Redraws the highlighted copy and the line numbers.
         */
        refresh: function () {
            var text, lines, numbers = [], i;

            if (!this.input) {
                return;
            }
            text = this.input.value;
            this.lastRendered = text;
            this.plainMode(text.length > HUGE);
            if (this.plainMode()) {
                this.code.textContent = '';
            } else {
                // The trailing space keeps the last (empty) line as tall as in the textarea.
                this.code.innerHTML = highlighter.toHtml(text, this.mode()) + ' \n';
            }
            lines = text.split('\n').length;
            if (lines !== this.lineCount) {
                for (i = 1; i <= lines; i++) {
                    numbers.push(i);
                }
                this.gutter.firstChild.textContent = numbers.join('\n') + '\n';
                this.lineCount = lines;
            }
            this.layout();
            this.updateCursor();
        },

        /**
         * Matches the highlighted layer to the textarea's inner box (without its scroll bars).
         */
        layout: function () {
            var input = this.input,
                style;

            if (!input || !this.highlightBox) {
                return;
            }
            style = window.getComputedStyle(input);
            $(this.highlightBox).css({
                top: input.offsetTop + parseFloat(style.borderTopWidth),
                left: input.offsetLeft + parseFloat(style.borderLeftWidth),
                width: input.clientWidth,
                height: input.clientHeight
            });
            $(this.gutter).css({
                paddingTop: parseFloat(style.paddingTop) + parseFloat(style.borderTopWidth),
                height: input.offsetHeight
            });
            this.syncScroll();
        },

        syncScroll: function () {
            if (!this.input) {
                return;
            }
            this.highlightBox.scrollTop = this.input.scrollTop;
            this.highlightBox.scrollLeft = this.input.scrollLeft;
            this.gutter.scrollTop = this.input.scrollTop;
        },

        updateCursor: function () {
            var input = this.input,
                before;

            if (!input) {
                return;
            }
            if (document.activeElement !== input) {
                this.cursorText('');

                return;
            }
            before = input.value.slice(0, input.selectionStart);
            this.cursorText(
                $.mage.__('Line %1, column %2')
                    .replace('%1', before.split('\n').length)
                    .replace('%2', before.length - before.lastIndexOf('\n'))
            );
        },

        /**
         * Tab indents (Shift+Tab outdents) so templates can be indented. Esc first lets Tab move the focus,
         * so keyboard users are never trapped in the field.
         */
        onKeyDown: function (event) {
            if (event.key === 'Escape') {
                this.tabMovesFocus(true);

                return;
            }
            if (event.key !== 'Tab' || event.ctrlKey || event.altKey || event.metaKey) {
                if (event.key !== 'Shift') {
                    this.tabMovesFocus(false);
                }

                return;
            }
            if (this.tabMovesFocus()) {
                this.tabMovesFocus(false);

                return;
            }
            event.preventDefault();
            this.indent(event.shiftKey);
        },

        indent: function (outdent) {
            var input = this.input,
                value = input.value,
                start = input.selectionStart,
                end = input.selectionEnd,
                collapsed = start === end,
                lineStart = value.lastIndexOf('\n', start - 1) + 1,
                lineEnd,
                block,
                changed,
                firstShift;

            if (!outdent && start === end) {
                this.insert(INDENT, start, end);

                return;
            }
            if (end > start && value.charAt(end - 1) === '\n') {
                end--;
            }
            lineEnd = value.indexOf('\n', end);
            lineEnd = lineEnd === -1 ? value.length : lineEnd;
            block = value.slice(lineStart, lineEnd);
            if (outdent) {
                changed = block.replace(/^(?: {1,4}|\t)/gm, '');
                firstShift = block.length && /^(?: {1,4}|\t)/.exec(block);
                firstShift = firstShift ? -firstShift[0].length : 0;
            } else {
                changed = block.replace(/^/gm, INDENT);
                firstShift = INDENT.length;
            }
            if (changed === block) {
                return;
            }
            this.insert(changed, lineStart, lineEnd);
            if (collapsed) {
                input.setSelectionRange(Math.max(lineStart, start + firstShift), Math.max(lineStart, start + firstShift));
            } else {
                input.setSelectionRange(Math.max(lineStart, start + firstShift), lineStart + changed.length);
            }
            $(input).trigger('input');
        },

        /**
         * Replaces a range through the browser's editing commands, so Undo (Ctrl+Z) keeps working.
         */
        insert: function (text, start, end) {
            var input = this.input,
                done = false;

            input.focus();
            input.setSelectionRange(start, end);
            try {
                done = document.execCommand('insertText', false, text);
            } catch (e) {
                done = false;
            }
            if (!done) {
                input.setRangeText(text, start, end, 'end');
                $(input).trigger('input');
            }
        },

        /**
         * Puts the cursor on a line (1-based), selects it and scrolls it into view.
         */
        goToLine: function (line) {
            var input = this.input,
                lines,
                start = 0,
                i,
                lineHeight;

            if (!input) {
                return;
            }
            lines = input.value.split('\n');
            line = Math.max(1, Math.min(line, lines.length));
            for (i = 0; i < line - 1; i++) {
                start += lines[i].length + 1;
            }
            input.focus();
            input.setSelectionRange(start, start + lines[line - 1].length);
            lineHeight = parseFloat(window.getComputedStyle(input).lineHeight) || 18;
            input.scrollTop = Math.max(0, (line - 1) * lineHeight - input.clientHeight / 3);
            this.markLine(line, lineHeight);
            this.syncScroll();
            this.updateCursor();
        },

        /**
         * Tints a line (the one named in an error) until the template is edited.
         */
        markLine: function (line, lineHeight) {
            var marker = $(this.highlightBox).find('.softaware-datafeed-editor-marker');

            if (!marker.length) {
                marker = $('<span class="softaware-datafeed-editor-marker"></span>').appendTo(this.highlightBox);
            }
            marker.css({
                top: parseFloat(window.getComputedStyle(this.input).paddingTop) + (line - 1) * lineHeight,
                height: lineHeight
            }).show();
            $(this.input).one('input', function () {
                marker.hide();
            });
        }
    });
});
