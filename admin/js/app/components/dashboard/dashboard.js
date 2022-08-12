/**!
 * violetCMS – Dashboard
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "knockout-mapping", "ajax", "chart"], function(ko, koMapping, ajax, Chart) {
    "use strict";

    /**
     * A place for all access statistics/logs
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Dashboard() {

        const self = this;


        /* ----- VIEW MODELS ----- */

        self.Statistics = {
            today : ko.observable(0),
            week  : ko.observable(0),
            month : ko.observable(0),
            byDay : ko.observableArray([])
        };

        self.TopTenURLs = ko.observableArray([]);
        self.LastModified = ko.observableArray([]);


        /* ----- COMPUTED ----- */

        self.StatisticsByDay = ko.pureComputed(function() {
            /**
             * Prevent race condition, where dashboard
             * may already be loaded, but language not
             */
            if (!ko.languageFormats()) {
                return null;
            }
            return {
                labels : ko.languageFormats().weekdaysShort,
                data   : self.Statistics.byDay()
            };
        });


        /* ----- CHART BINDING ----- */

        ko.bindingHandlers.chart = {
            init: function(element) {
                const chart = new Chart(element);
                ko.utils.domData.set(element, "Chart", chart);
                ko.utils.domNodeDisposal.addDisposeCallback(element, function() {
                    chart.dispose();
                });
            },
            update: function(element, valueAccessor) {
                const value = ko.unwrap(valueAccessor());
                const chart = ko.utils.domData.get(element, "Chart");
                if (chart && value) {
                    chart.setData(value);
                }
            }
        };


        /* ----- INIT ----- */

        self.update();
    }

    Dashboard.prototype.update = function() {
        const self = this;
        ajax.get("?q=dashboard", function(data) {
            if (data) {
                koMapping.fromJS(data.Dashboard.statistics, {}, self.Statistics);
                koMapping.fromJS(data.Dashboard.topTenURLs, {}, self.TopTenURLs);
                koMapping.fromJS(data.Dashboard.lastModified, {}, self.LastModified);
            }
        }, null);
    };

    Dashboard.prototype.dispose = function() {};

    return {
        viewModel: Dashboard,
        template: { require: "text!components/dashboard/dashboard.html" }
    };

});
