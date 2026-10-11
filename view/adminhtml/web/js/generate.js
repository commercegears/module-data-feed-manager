/**
 * Runs a feed generation batch by batch and shows the progress.
 */
define(['jquery'], function ($) {
    'use strict';

    function format(text) {
        var args = Array.prototype.slice.call(arguments, 1);

        return String(text).replace(/%(\d+)/g, function (match, index) {
            var value = args[index - 1];

            return value === undefined ? match : value;
        });
    }

    function escapeHtml(text) {
        return $('<div/>').text(String(text)).html();
    }

    function fileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        }
        if (bytes < 1048576) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    return function (config, element) {
        var $root = $(element),
            $bar = $root.find('[data-role="bar"]'),
            $text = $root.find('[data-role="text"]'),
            $result = $root.find('[data-role="result"]'),
            $restart = $root.find('[data-role="restart"]'),
            labels = config.labels;

        function setProgress(progress) {
            $bar.css('width', progress.percent + '%');
            $root.find('[role="progressbar"]').attr('aria-valuenow', progress.percent);
            $text.text(format(labels.progress, progress.processed, progress.total, progress.items));
        }

        function showError(message, locked) {
            $bar.addClass('is-error');
            $result.html('<div class="message message-error"><div>' + escapeHtml(message) + '</div></div>');
            if (locked) {
                $result.append(
                    $('<button type="button" class="action-secondary"/>').text(labels.force).on('click', function () {
                        start(true);
                    })
                );
            }
            $restart.prop('hidden', false);
        }

        function showResult(result) {
            var severity = result.status === 'success' ? 'success' : (result.status === 'warning' ? 'warning' : 'error'),
                html = '<div class="message message-' + severity + '"><div>' + escapeHtml(labels[result.status] || result.status) + '</div></div>',
                facts = [
                    [labels.items, result.items],
                    [labels.errors, result.errors],
                    [labels.size, fileSize(result.file_size)],
                    [labels.duration, format(labels.seconds, result.duration)]
                ];

            html += '<table class="commercegears-datafeed-facts">';
            facts.forEach(function (fact) {
                html += '<tr><th>' + escapeHtml(fact[0]) + '</th><td>' + escapeHtml(fact[1]) + '</td></tr>';
            });
            if (result.url) {
                html += '<tr><th>URL</th><td><a href="' + escapeHtml(result.url) + '" target="_blank" rel="noopener">' +
                    escapeHtml(result.url) + '</a></td></tr>';
            }
            html += '</table>';
            if (result.messages && result.messages.length) {
                html += '<h3>' + escapeHtml(labels.messages) + '</h3><ul class="commercegears-datafeed-messages">';
                result.messages.forEach(function (message) {
                    html += '<li>' + escapeHtml(message) + '</li>';
                });
                html += '</ul>';
            }
            $bar.css('width', '100%').toggleClass('is-error', result.status === 'error');
            $result.html(html);
            $restart.prop('hidden', false);
        }

        function request(action, force) {
            return $.ajax({
                url: config.stepUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    id: config.feedId,
                    'step_action': action,
                    force: force ? 1 : 0,
                    'form_key': window.FORM_KEY
                }
            });
        }

        function step() {
            request('step').done(function (response) {
                if (!response.ok) {
                    showError(response.message, false);
                    return;
                }
                setProgress(response.progress);
                if (response.result) {
                    showResult(response.result);
                    return;
                }
                if (response.progress.done) {
                    $text.text(labels.finishing);
                }
                step();
            }).fail(function (xhr) {
                showError(format(labels.network, xhr.status + ' ' + xhr.statusText), false);
            });
        }

        function start(force) {
            $restart.prop('hidden', true);
            $result.empty();
            $bar.removeClass('is-error').css('width', '0');
            $text.text(labels.starting);
            request('start', force).done(function (response) {
                if (!response.ok) {
                    showError(response.message, response.locked);
                    return;
                }
                setProgress(response.progress);
                step();
            }).fail(function (xhr) {
                showError(format(labels.network, xhr.status + ' ' + xhr.statusText), false);
            });
        }

        $restart.on('click', function () {
            start(false);
        });

        if (config.locked) {
            showError(labels.locked, true);
        } else {
            start(false);
        }
    };
});
