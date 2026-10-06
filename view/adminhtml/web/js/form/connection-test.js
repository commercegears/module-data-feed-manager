/**
 * Feed form: tests the FTP/SFTP settings currently entered.
 */
define(['jquery', 'uiElement', 'mage/translate'], function ($, Element, $t) {
    'use strict';

    return Element.extend({
        defaults: {
            template: 'Softaware_DataFeed/form/connection-test',
            url: '',
            visible: true,
            loading: false,
            message: '',
            messageType: ''
        },

        initObservable: function () {
            this._super().observe(['visible', 'loading', 'message', 'messageType']);

            return this;
        },

        run: function () {
            var self = this;

            this.loading(true);
            this.message('');
            $.ajax({
                url: this.url,
                type: 'POST',
                dataType: 'json',
                data: {feed: JSON.stringify(this.source.get('data')), 'form_key': window.FORM_KEY}
            }).done(function (response) {
                self.messageType(response.ok ? 'success' : 'error');
                self.message(response.message);
            }).fail(function (xhr) {
                self.messageType('error');
                self.message($t('The test could not be run.') + ' (' + xhr.status + ')');
            }).always(function () {
                self.loading(false);
            });
        }
    });
});
