/**!
 * violetCMS – Pages Management (Sitemap)
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "knockout-mapping", "knockout-sortable", "ajax", "slugify/slugify"], function(ko, koMapping, koSortable, ajax, slugify) {
    "use strict";

    /**
     * Handles the creation and sorting of pages
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Sitemap(params) {

        const self = this;


        /* ----- VIEW MODELS ----- */

        self.Sitemap = ko.observableArray();

        self.PageIcon = function(data) {
            return ko.pureComputed(function() {
                const isHome = (data.url() === params.config.Routes.Home());
                return (isHome ? "icon-home" : "icon-page") + (data.published() ? (data.visible() ? "" : " invisible") : " unpublished");
            }, self);
        };

        self.Page = function() {
            this.title     = ko.observable(null);
            this.slug      = ko.observable(null);
            this.template  = ko.observable("default");
            this.visible   = ko.observable(null);
            this.published = ko.observable(null);
        };

        self.Templates = ko.observableArray();

        self.TemplateSelect = ko.pureComputed(function() {
            const templates = [];
            ko.utils.arrayForEach(self.Templates(), function(template) {
                const templateName = template.name();
                const prettyName = templateName.charAt(0).toUpperCase() + templateName.substr(1).toLowerCase();
                if (templateName.toLowerCase() !== "error") {
                    templates.push({ title: prettyName, value: templateName });
                }
            });
            return templates;
        });


        /* ----- HELPERS ----- */

        self.newPage = ko.observable(null);
        self.confirmDelete = ko.observable(null);


        /* ----- REGISTER DIALOGS ----- */

        if (!ko.components.isRegistered("newPage")) {
            ko.components.register("newPage", {
                viewModel: { require: "dialog/dialog" },
                template: { require: "text!dialog/page-new.html" }
            });
        }

        if (!ko.components.isRegistered("deletePage")) {
            ko.components.register("deletePage", {
                viewModel: { require: "dialog/dialog" },
                template: { require: "text!dialog/page-delete.html" }
            });
        }


        /* ----- INIT ----- */

        self.update();

    }

    Sitemap.prototype.update = function() {
        const self = this;
        ajax.get("?q=sitemap,templates", function(data) {
            if (data) {
                koMapping.fromJS(data.Sitemap, {}, self.Sitemap);
                koMapping.fromJS(data.Templates, {}, self.Templates);
            }
        }, null);
    };

    Sitemap.prototype.Slugify = function(item, changedProps) {
        const page = ko.unwrap(item);
        if (changedProps.includes("title")) {
            page.slug(slugify(page.title()));
        } else if (changedProps.includes("slug")) {
            page.slug(slugify(page.slug()));
        }
        return true;
    };

    Sitemap.prototype.addPage = function() {
        const self = this;
        self.newPage(new self.Page());
    };

    Sitemap.prototype.createPage = function(data) {
        const self = this;
        ajax.post("?q=sitemap&action=create", koMapping.toJSON(data), function(response) {
            if (response) {
                koMapping.fromJS(response.Sitemap, {}, self.Sitemap);
            }
        });
    };

    Sitemap.prototype.movePage = function(event, data) {
        const self = ko.contextFor(event.to).$component;

        const source = data.url();
        const targetVM = ko.dataFor(event.to);
        const target = targetVM.url ? targetVM.url() : "/";
        const index = event.newIndex;

        const request = {
            source: source,
            target: target,
            index : index
        };
        ajax.post("?q=sitemap&action=move", koMapping.toJSON(request), function(response) {
            if (response) {
                koMapping.fromJS(response.Sitemap, {}, self.Sitemap);
            }
        });
    };

    Sitemap.prototype.getConfirmDelete = function(data) {
        const self = this;
        self.confirmDelete(data);
    };

    Sitemap.prototype.deletePage = function(data) {
        const self = this;
        ajax.post("?q=sitemap&action=delete", koMapping.toJSON(data), function(response) {
            if (response) {
                koMapping.fromJS(response.Sitemap, {}, self.Sitemap);
            }
        });
    };

    Sitemap.prototype.dispose = function() {};

    return {
        viewModel: Sitemap,
        template: { require: "text!components/sitemap/sitemap.html" }
    };

});
