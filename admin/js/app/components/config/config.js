/**!
 * violetCMS – Configuration
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

/* global i18n */
define(["knockout", "knockout-mapping", "ajax", "i18n"], function(ko, koMapping, ajax) {
    "use strict";

    /**
     * Handles setting of VioletCMS configuration and
     * shows some info about the current server setup
     * Note: The config has already been loaded in admin
     * This is only for changing/saving the config
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Config(params) {

        const self = this;

        self.subscriptions = {};


        /* ----- TAB NAV ----- */

        self.navTabs = ko.pureComputed(function() {
            return { tabs: [i18n("TabConfigWebsite"), i18n("TabConfigSettings"), i18n("TabConfigInfo")]};
        }, self).extend({ deferred: true });


        /* ----- VIEW MODELS ----- */

        self.Sitemap = ko.observableArray();
        self.Themes = ko.observableArray();
        self.PHPInfo = {
            Version  : ko.observable(),
            Sections : ko.observable()
        };


        /* ----- COMPUTED ----- */

        /**
         * Top level sitemap entries for route selection
         *
         * @param {boolean} addThemeErrorRoute Add option to select theme error route
         */

        self.RouteSelect = function(addThemeErrorRoute) {
            return ko.pureComputed(function() {
                const options = [];
                if (addThemeErrorRoute) {
                    options.push({
                        title: i18n("OptThemeErrorPage"),
                        value: "/error"
                    }, { title: null, value: null });
                }
                ko.utils.arrayForEach(self.Sitemap(), function(node) {
                    if (node.published()) {
                        options.push({
                            title: node.title(),
                            value: node.url()
                        });
                    }
                });
                return options;
            }, self);
        };

        /**
         * Available themes
         */

        self.ThemeSelect = ko.pureComputed(function() {
            const options = [];
            ko.utils.arrayForEach(self.Themes(), function(theme) {
                options.push({ title: theme, value: theme });
            });
            return options;
        }, self);


        /* ----- HELPER FUNCTIONS ----- */

        self.addMetaTag = function() {
            params.config.Website.Meta.push({
                name    : ko.observable(""),
                content : ko.observable("")
            });
        };

        self.removeMetaTag = function() {
            params.config.Website.Meta.remove(this);
            if (params.config.Website.Meta().length === 0) {
                self.addMetaTag();
            }
        };


        /* ----- INIT ----- */

        self.update(params.config);

    }

    Config.prototype.update = function(config) {
        const self = this;
        ajax.get("?q=sitemap,themes,info", function(data) {
            if (data) {
                koMapping.fromJS(data.Sitemap, {}, self.Sitemap);
                koMapping.fromJS(data.Themes, {}, self.Themes);
                koMapping.fromJS(data.Info, {}, self.PHPInfo);

                if (config.Website.Meta().length === 0) {
                    self.addMetaTag();
                }
                config.dirtyFlag.setClean();
                self.subscriptions["config"] = config.dirtyFlag.isDirty.subscribe(self.saveConfig, config);
            }
        }, null);
    };

    Config.prototype.saveConfig = function() {
        const config = this;
        const mappingKeys = Object.keys(config).filter(function(key) {
            return key !== "dirtyFlag" && key !== "__ko_mapping__";
        });
        const mapping = {
            "include": mappingKeys
        };
        ajax.post("?q=config", koMapping.toJSON(config, mapping));
        config.dirtyFlag.setClean();
    };

    Config.prototype.dispose = function() {
        const self = this;
        for (const key in self.subscriptions) {
            self.subscriptions[key].dispose();
            self.subscriptions[key] = null;
        }
    };

    return {
        viewModel: Config,
        template: { require: "text!components/config/config.html" }
    };

});
