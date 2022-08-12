/**!
 * violetCMS – Plugin Settings
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "knockout-mapping", "ajax"], function(ko, koMapping, ajax) {
    "use strict";

    /**
     * Handles plugin settings
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Plugin(params) {

        const self = this;


        /* ----- VIEW MODELS ----- */

        self.PluginName = params.request()[0];

        self.Plugin = {
            name    : ko.observable(),
            enabled : ko.observable(),
            info    : {
                version     : ko.observable(),
                description : ko.observable(),
                author      : ko.observable(),
                email       : ko.observable(),
                homepage    : ko.observable(),
                license     : ko.observable()
            },
            config  : ko.observableArray()
        };

        self.Sitemap = ko.observableArray([]);


        /* ----- COMPUTED ----- */

        self.PageSelect = ko.pureComputed(function() {
            const links = [];
            function walkSitemap(node, indent) {
                ko.utils.arrayForEach(node, function(page) {
                    links.push({
                        title: indent + page.title(),
                        value: page.url()
                    });
                    walkSitemap(page.children(), "⇢ " + indent); // → or ⇢ ?
                });
            }
            walkSitemap(self.Sitemap(), "");
            return links;
        }, self);


        /* ----- HELPERS ----- */

        self.confirmDelete = ko.observable(null);


        /* ----- REGISTER DIALOGS ----- */

        if (!ko.components.isRegistered("deletePlugin")) {
            ko.components.register("deletePlugin", {
                viewModel: { require: "dialog/dialog" },
                template: { require: "text!dialog/plugin-delete.html" }
            });
        }


        /* ----- INIT ----- */

        self.update();
    }

    Plugin.prototype.update = function() {
        const self = this;
        ajax.get("?q=sitemap,plugins&name="+self.PluginName, function(data) {
            if (data) {
                koMapping.fromJS(data.Sitemap, {}, self.Sitemap);
                koMapping.fromJS(data.Plugins, {}, self.Plugin);
            }
        });
    };

    Plugin.prototype.saveConfig = function() {
        const self = this;
        ajax.post("?q=plugins&action=update&name="+self.PluginName, koMapping.toJSON(self.Plugin));
    };

    Plugin.prototype.getConfirmDelete = function() {
        const self = this;
        self.confirmDelete(self.PluginName);
    };

    Plugin.prototype.deletePlugin = function() {
        const self = this;
        ajax.post("?q=plugins&action=delete&name="+self.PluginName, null);
        window.location.hash = "!/plugins"; // not the smartest solution, but it works
    };

    Plugin.prototype.dispose = function() {};

    return {
        viewModel: Plugin,
        template: { require: "text!components/plugin/plugin.html" }
    };

});
