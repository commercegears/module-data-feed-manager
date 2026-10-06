/**
 * Feed form: renders the first items of the feed with the values currently in the form.
 */
define(['jquery', 'uiElement', 'mage/translate'], function ($, Element, $t) {
    'use strict';

    return Element.extend({
        defaults: {
            template: 'Softaware_DataFeed/form/preview',
            url: '',
            loading: false,
            output: '',
            message: '',
            messageType: '',
            errors: [],
            summary: ''
        },

        initObservable: function () {
            this._super().observe(['loading', 'output', 'message', 'messageType', 'errors', 'summary']);

            return this;
        },

        run: function () {
            var self = this,
                data = $.extend(true, {}, this.source.get('data'));

            delete data['ftp_password'];
            this.loading(true);
            this.message('');
            this.errors([]);
            $.ajax({
                url: this.url,
                type: 'POST',
                dataType: 'json',
                data: {feed: JSON.stringify(data), 'form_key': window.FORM_KEY}
            }).done(function (response) {
                if (!response.ok) {
                    self.output('');
                    self.summary('');
                    self.messageType('error');
                    self.message(response.message);
                    return;
                }
                self.output(response.output);
                self.summary($t('%1 items shown, %2 products match the filters.')
                    .replace('%1', response.items).replace('%2', response.total));
                self.errors(response.errors || []);
                if (response.valid) {
                    self.messageType('success');
                    self.message($t('Valid: the preview parses without errors.'));
                } else {
                    self.messageType('error');
                    self.message(response.validation);
                }
            }).fail(function (xhr) {
                self.messageType('error');
                self.message($t('The preview could not be loaded.') + ' (' + xhr.status + ')');
            }).always(function () {
                self.loading(false);
            });
        }
    });
});
