/**
 * Grid with an empty state: when the table has no records at all (no filter or search applied), it shows a short
 * explanation and the primary action instead of "We couldn't find any records."
 */
define([
    'underscore',
    'Magento_Ui/js/grid/listing'
], function (_, Listing) {
    'use strict';

    return Listing.extend({
        defaults: {
            template: 'CommerceGears_DataFeed/grid/listing',
            emptyTitle: '',
            emptyText: '',
            emptyActionLabel: '',
            emptyActionUrl: '',
            appliedFilters: {},
            searchQuery: '',
            imports: {
                appliedFilters: '${ $.provider }:params.filters',
                searchQuery: '${ $.provider }:params.search'
            },
            tracks: {
                appliedFilters: true,
                searchQuery: true
            }
        },

        /**
         * @returns {Boolean}
         */
        isFiltered: function () {
            var filters = _.omit(this.appliedFilters || {}, 'placeholder');

            return !!this.searchQuery || _.some(filters, function (value) {
                return _.isObject(value) ? _.some(value, Boolean) : !!value;
            });
        },

        /**
         * @returns {Boolean}
         */
        showEmptyState: function () {
            return !!this.emptyTitle && !this.hasData() && !this.isFiltered();
        }
    });
});
