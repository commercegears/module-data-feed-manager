/**
 * Google category autocomplete and row filter on the category mapping page.
 */
define(['jquery', 'jquery/ui-modules/widgets/autocomplete'], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);

        $root.find('[data-role="taxonomy"]').each(function () {
            $(this).autocomplete({
                source: config.searchUrl,
                minLength: 2,
                delay: 200
            });
        });

        $root.find('[data-role="filter"]').on('input', function () {
            var term = $.trim($(this).val()).toLowerCase();

            $root.find('[data-role="row"]').each(function () {
                var $row = $(this),
                    match = !term || $row.data('name').indexOf(term) !== -1 ||
                        String($row.find('[data-role="taxonomy"]').val()).toLowerCase().indexOf(term) !== -1;

                $row.toggle(match);
            });
        }).on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
    };
});
