/**
 * Feed form: renders the first items of the feed with the values currently in the form (unsaved changes included)
 * and shows them highlighted, inline (Preview tab) or in a modal (Content tab and the "Preview" page button).
 * Nothing is saved or written.
 */
define([
    'jquery',
    'uiElement',
    'uiRegistry',
    'mage/translate',
    'Softaware_DataFeed/js/highlight',
    'Magento_Ui/js/modal/modal'
], function ($, Element, registry, $t, highlighter) {
    'use strict';

    return Element.extend({
        defaults: {
            template: 'Softaware_DataFeed/form/preview',
            url: '',
            editorName: '',
            tabName: '',
            loading: false,
            output: '',
            outputHtml: '',
            message: '',
            messageType: '',
            errors: [],
            summary: '',
            templateLine: 0,
            outputLine: 0
        },

        initObservable: function () {
            this._super().observe([
                'loading', 'output', 'outputHtml', 'message', 'messageType', 'errors', 'summary',
                'templateLine', 'outputLine'
            ]);

            return this;
        },

        run: function () {
            var self = this,
                data = $.extend(true, {}, this.source.get('data'));

            delete data['ftp_password'];
            this.loading(true);
            this.message('');
            this.errors([]);
            this.templateLine(0);
            this.outputLine(0);
            this.renderModal();

            return $.ajax({
                url: this.url,
                type: 'POST',
                dataType: 'json',
                data: {feed: JSON.stringify(data), 'form_key': window.FORM_KEY}
            }).done(function (response) {
                self.apply(response);
            }).fail(function (xhr) {
                self.output('');
                self.outputHtml('');
                self.summary('');
                self.messageType('error');
                self.message($t('The preview could not be loaded.') + ' (' + xhr.status + ')');
            }).always(function () {
                self.loading(false);
                self.renderModal();
            });
        },

        apply: function (response) {
            var summary,
                format = this.source.get('data.format');

            // Only XML and JSON feeds have a template to jump to (CSV/TXT errors name the column).
            this.templateLine(format === 'xml' || format === 'json' ? response.templateLine || 0 : 0);
            if (!response.ok) {
                this.output('');
                this.outputHtml('');
                this.summary('');
                this.messageType('error');
                this.message(response.message);

                return;
            }
            this.output(response.output);
            this.outputHtml(highlighter.toLines(response.output, this.modeFor(response.format), {
                delimiter: response.delimiter
            }));
            summary = $t('%1 items shown, %2 products match the filters.')
                .replace('%1', response.items).replace('%2', response.total);
            // Products are counted once; in the variant modes each variant of a configurable is its own item.
            if (response.configurable > 0) {
                summary += ' ' + (response.mode === 'both'
                    ? $t('%1 of them are configurable products: the feed has an item for each of them and for each of their variants, so it has more items than matching products.')
                    : $t('%1 of them are configurable products: the feed has an item for each of their variants instead, so it has more items than matching products.')
                ).replace('%1', response.configurable);
            }
            if (response.last_items !== null && response.last_items !== undefined) {
                summary += ' ' + $t('The last generated file has %1 items.').replace('%1', response.last_items);
            }
            if (response.truncated) {
                summary += ' ' + $t('The preview stopped early: most products produced no item.');
            }
            this.summary(summary);
            this.errors(response.errors || []);
            this.outputLine(response.outputLine || 0);
            if (response.valid) {
                this.messageType('success');
                this.message($t('Valid file format: the preview parses without errors. Channel rules (for example required attributes) are not checked.'));
            } else {
                this.messageType('error');
                this.message(response.validation);
            }
        },

        modeFor: function (format) {
            if (format === 'xml' || format === 'json') {
                return format;
            }

            return 'csv';
        },

        /**
         * Opens the Content tab and puts the cursor on the template line named in the error.
         */
        showTemplateLine: function () {
            var line = this.templateLine();

            if (this.modalElement) {
                this.modalElement.modal('closeModal');
            }
            if (this.tabName) {
                registry.get(this.tabName, function (tab) {
                    if (typeof tab.activate === 'function') {
                        tab.activate();
                    }
                });
            }
            registry.get(this.editorName, function (editor) {
                // After the modal has closed and given the focus back to the button that opened it.
                window.setTimeout(function () {
                    editor.goToLine(line);
                }, 400);
            });
        },

        /**
         * Scrolls the output to the line named by the validation message and marks it.
         */
        markOutputLine: function (container) {
            var line = this.outputLine(),
                $line;

            $(container).find('.sw-hl-line.is-error').removeClass('is-error');
            if (!line) {
                return;
            }
            $line = $(container).find('.sw-hl-line').eq(line - 1).addClass('is-error');
            if ($line.length) {
                container.scrollTop = Math.max(0, $line[0].offsetTop - container.clientHeight / 3);
            }
        },

        /**
         * KO afterRender of the inline output: mark the error line once the output is shown.
         */
        onOutputRendered: function (element) {
            var self = this;

            this.inlineOutput = element;
            this.outputHtml.subscribe(function () {
                window.setTimeout(function () {
                    self.markOutputLine(element);
                }, 0);
            });
        },

        /**
         * Modal mode (Content tab button and page button).
         */
        openModal: function () {
            var self = this;

            if (!this.modalElement) {
                this.modalElement = $('<div class="softaware-datafeed-preview-modal"></div>').modal({
                    type: 'slide',
                    title: $t('Feed Preview'),
                    modalClass: 'softaware-datafeed-preview-slide',
                    buttons: [{
                        text: $t('Refresh'),
                        'class': 'action-secondary',
                        click: function () {
                            self.run();
                        }
                    }, {
                        text: $t('Close'),
                        'class': 'action-primary',
                        click: function () {
                            this.closeModal();
                        }
                    }]
                });
            }
            this.modalElement.modal('openModal');
            this.run();
        },

        /**
         * Builds the modal body from the current state (text is escaped; the output HTML comes escaped from
         * the highlighter).
         */
        renderModal: function () {
            var self = this,
                $body,
                $list,
                $output;

            if (!this.modalElement) {
                return;
            }
            $body = $('<div class="softaware-datafeed-preview"></div>');
            $body.append($('<p class="softaware-datafeed-muted"></p>').text(
                $t('Rendered from the values in the form, including unsaved changes, for the first matching products. Nothing is saved or written.')
            ));
            if (this.loading()) {
                $body.append($('<div class="softaware-datafeed-spinner" role="status"></div>').text($t('Rendering…')));
            }
            if (this.message()) {
                $body.append(
                    $('<div role="alert"></div>').attr('class', 'message message-' + this.messageType())
                        .append($('<div></div>').text(this.message()))
                );
            }
            if (this.templateLine() && this.editorName) {
                $body.append($('<p></p>').append(
                    $('<button type="button" class="action-secondary"></button>')
                        .text($t('Show line %1 in the template').replace('%1', this.templateLine()))
                        .on('click', function () {
                            self.showTemplateLine();
                        })
                ));
            }
            if (this.summary()) {
                $body.append($('<p></p>').text(this.summary()));
            }
            if (this.errors().length) {
                $list = $('<ul class="softaware-datafeed-messages"></ul>');
                $.each(this.errors(), function (i, error) {
                    $list.append($('<li></li>').text(error));
                });
                $body.append($list);
            }
            if (this.outputHtml()) {
                $output = $('<pre class="softaware-datafeed-output" tabindex="0"></pre>')
                    .attr('aria-label', $t('Preview output'))
                    .html(this.outputHtml());
                $body.append($output);
            }
            this.modalElement.empty().append($body);
            if ($output) {
                this.markOutputLine($output[0]);
            }
        }
    });
});
