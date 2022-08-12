/**!
 * violetCMS – Page Content
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

/* global i18n */
define(["knockout", "knockout-mapping", "knockout-pikaday", "ajax", "utils", "i18n"], function(ko, koMapping, koPikaday, ajax, ux) {
    "use strict";

    /**
     * Handles page content
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Page(params) {

        const self = this;


        /* ----- TAB NAV ----- */

        self.navTabs = ko.pureComputed(function() {
            return { tabs: [i18n("TabPageContent"), i18n("TabPageMeta"), i18n("TabPageOptions")]};
        }, self).extend({ deferred: true });


        /* ----- VIEW MODELS ----- */

        self.PageURL = "/"+params.request()[0];
        self.RootURL = ko.observable();

        self.Sitemap   = ko.observableArray([]);
        self.Plugins   = ko.observableArray([]);
        self.Templates = ko.observableArray([]);

        self.Editor = {
            ready: ko.observable(false),
            hasChanges: ko.observable(false),
            getValue: () => {},
            setValue: () => {},
            Sitemap: self.Sitemap,
            Plugins: self.Plugins
        };


        /**
         * @member {observable} self.PageContent - Page content
         * @member {object}     self.Page        - Page meta data
         */

        /** @todo maybe add additional page meta fields (like in config for site) */

        self.PageContent = ko.observable("");
        self.Page = {
            title         : ko.observable(null),
            template      : ko.observable(null).extend({ notify: "always" }),
            description   : ko.observable(null),
            keywords      : ko.observable(null),
            canonicalURL  : ko.observable(null),
            redirectURL   : ko.observable(null),
            robots        : ko.observable("index,follow"),
            published     : ko.observable(true).extend({ boolean: true }),
            visible       : ko.observable(true).extend({ boolean: true }),
            date          : ko.observable(null),
            publishDate   : ko.observable(null),
            unpublishDate : ko.observable(null)
        };

        self.PageContent.dirtyFlag = new ko.dirtyFlag(self.PageContent);
        self.Page.dirtyFlag = new ko.dirtyFlag(self.Page);

        self.Page.template.subscribe(function(value) {
            if (value !== null) {
                const template = ko.utils.arrayFirst(self.Templates(), function(template) {
                    return template.name() === value;
                });
                if (template && template.sections().length > 1) {
                    const sections = ko.utils.arrayMap(template.sections(), function(name, index) {
                        return new self.PageSection(name, index);
                    });
                    self.selectedPageSection(null);
                    self.PageSections(sections);
                    self.PageSections()[0].select();
                } else {
                    let content = "";
                    if (self.PageSections().length > 1) {
                        const currentPageSection = self.selectedPageSection();
                        if (currentPageSection) {
                            const index = currentPageSection.index();
                            content = self.getSectionContent(index);
                        }
                    } else {
                        content = self.getSectionContent(0);
                    }
                    self.selectedPageSection(null);
                    self.PageSections([]);
                    if (self.Editor.ready()) {
                        self.Editor.setValue(content);
                        self.Editor.hasChanges(false);
                    }
                }
            }
        });


        /**
         * @member {pureComputed} self.PageSections - Array of page sections
         * @member {function}     self.PageSection  - A page section
         */

        self.PageSections = ko.observableArray([]);
        self.PageSection = function(name, index) {
            this.name = ko.observable(name);
            this.displayName = ko.pureComputed(function() {
                const t = this.name();
                if (t === null) {
                    return i18n("TabPageContent");
                } else {
                    return t.charAt(0).toUpperCase() + t.substr(1).toLowerCase();
                }
            }, this);
            this.index = ko.observable(index);
            this.selected = ko.pureComputed(function() {
                return this === self.selectedPageSection();
            }, this);
            this.select = function() {
                /**
                 * get currently selected section and update self.PageContent
                 * before actually changing the selection
                 */
                const currentPageSection = self.selectedPageSection();
                if (currentPageSection) {
                    const index = currentPageSection.index();
                    if (self.Editor.ready()) {
                        self.setSectionContent(index, self.Editor.getValue());
                    }
                }

                /**
                 * Select this page section which also updates editor
                 */
                self.selectedPageSection(this);
            };
        };

        self.selectedPageSection = ko.observable(null);

        self.selectedPageSection.subscribe(function(section) {
            if (section !== null) {
                const index = section.index();
                const content = self.getSectionContent(index);
                if (self.Editor.ready()) {
                    self.Editor.setValue(content || "");
                    self.Editor.hasChanges(false);
                }
            }
        });

        self.showPageSections = ko.pureComputed(function() {
            return self.PageSections().length > 1;
        });


        /* ----- WRITABLE COMPUTED ----- */

        /**
         * self.Page.robots stores the combined value only,
         * but the backend provides separate checkboxes.
         * So we use 2 writables to calc the combined value
         */
        self.robotsIndex = ko.pureComputed({
            read: function () {
                if (self.Page.robots()) {
                    const robots = self.Page.robots().split(",").filter(item => item.indexOf("index") >= 0);
                    return robots.length === 1 && robots[0].trim() === "index";
                }
            },
            write: function (value) {
                const index = value ? "index" : "noindex";
                const follow = self.robotsFollow() ? "follow" : "nofollow";
                self.Page.robots(index+", "+follow);
            }
        });

        self.robotsFollow = ko.pureComputed({
            read: function () {
                if (self.Page.robots()) {
                    const robots = self.Page.robots().split(",").filter(item => item.indexOf("follow") >= 0);
                    return robots.length === 1 && robots[0].trim() === "follow";
                }
            },
            write: function (value) {
                const index = self.robotsIndex() ? "index" : "noindex";
                const follow = value ? "follow" : "nofollow";
                self.Page.robots(index+", "+follow);
            }
        });


        /* ----- COMPUTED ----- */

        /**
         * Options for the select template dropdowns
         * - hides the theme error template
         */
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

        self.showPreview = ko.observable(false);

        self.hasUnsavedChanges = ko.pureComputed(function() {
            return self.PageContent.dirtyFlag.isDirty() || self.Page.dirtyFlag.isDirty() || (self.Editor.ready() && self.Editor.hasChanges());
        });


        self.LastError = ko.observable();
        self.confirmClose = ko.observable(null);


        /* ----- REGISTER DIALOGS ----- */

        if (!ko.components.isRegistered("editor")) {
            ko.components.register("editor", { require: "components/editor/editor"});
        }

        if (!ko.components.isRegistered("mediabrowser")) {
            ko.components.register("mediabrowser", { require: "components/mediabrowser/mediabrowser" });
        }

        if (!ko.components.isRegistered("confirmClose")) {
            ko.components.register("confirmClose", {
                viewModel: { require: "dialog/dialog" },
                template: { require: "text!dialog/confirm-close.html" }
            });
        }


        /* ----- INIT ----- */

        self.Editor.ready.subscribe(function(value) {
            if (value === true) {
                self.update();
            }
        });

    }

    Page.prototype.update = function() {
        const self = this;
        ajax.get("?q=sitemap,templates,plugins,page&url="+self.PageURL, function(data) {
            if (data) {
                koMapping.fromJS(data.Sitemap, {}, self.Sitemap);
                koMapping.fromJS(data.Plugins, {}, self.Plugins);
                koMapping.fromJS(data.Templates, {}, self.Templates);
                koMapping.fromJS(data.Page.Content, {}, self.PageContent);
                koMapping.fromJS(data.Page.Frontmatter, {}, self.Page);
                koMapping.fromJS(data.Page.rootURL, {}, self.RootURL);
                self.PageContent.dirtyFlag.setClean();
                self.Page.dirtyFlag.setClean();
                self.Editor.hasChanges(false);
            }
        }, function(errMsg) {
            self.PageContent.dirtyFlag.setClean();
            self.Page.dirtyFlag.setClean();
            self.LastError(errMsg);
        });
    };

    Page.prototype.prepareOutput = function() {
        const self = this;

        if (!self.Editor.ready()) {
            return null;
        }

        let content = "";

        // Force content update
        if (self.PageSections().length > 0) {
            const index = self.selectedPageSection().index();
            self.setSectionContent(index, self.Editor.getValue());

            const sections = [];
            ko.utils.arrayForEach(self.PageSections(), function(noop, index) {
                sections.push(self.getSectionContent(index));
            });

            content = sections.join("\n~~~section-marker~~~\n");

        } else {
            content = self.Editor.getValue();
        }

        const frontmatter = {};
        ko.utils.arrayForEach(Object.keys(self.Page), function(key) {
            if (ko.isObservable(self.Page[key]) && ko.unwrap(self.Page[key]) !== null && ko.unwrap(self.Page[key]) !== "") {
                frontmatter[key] = ko.unwrap(self.Page[key]);
            }
        });

        return {
            Content: content,
            Frontmatter: frontmatter
        };
    };

    Page.prototype.save = function(callback) {
        const self = this;
        if (!self.LastError()) {

            const data = self.prepareOutput();
            if (data === null) {
                ux.notify("Unexpected error while trying to save document", "error");
                return;
            }

            ajax.post("?q=page&url="+self.PageURL, koMapping.toJSON(data), function() {
                self.PageContent(data.Content);
                self.PageContent.dirtyFlag.setClean();
                self.Page.dirtyFlag.setClean();
                self.Editor.hasChanges(false);
                if (callback && typeof callback === "function") {
                    callback();
                }
            });
        }
    };

    Page.prototype.saveDraft = function(callback) {
        const self = this;
        if (!self.LastError()) {

            const data = self.prepareOutput();
            if (data === null) {
                ux.notify("Unexpected error while trying to save document", "error");
                return;
            }

            ajax.post("?q=page&draft=true&url="+self.PageURL, koMapping.toJSON(data), function() {
                if (callback && typeof callback === "function") {
                    callback();
                }
            });
        }
    };

    Page.prototype.deleteDraft = function() {
        const self = this;
        ajax.post("?q=page&draft=false&url="+self.PageURL, null);
    };

    Page.prototype.preview = function() {
        const self = this;
        if (!self.showPreview()) {
            self.saveDraft(function() {
                self.showPreview(true);
            });
        } else {
            self.showPreview(false);
        }
    };

    Page.prototype.getSectionContent = function(index) {
        const self = this;
        const sections = self.PageContent().split("\n~~~section-marker~~~\n");
        if (sections.length > 0 && index <= (sections.length-1)) {
            return sections[index];
        }
        return "";
    };

    Page.prototype.setSectionContent = function(index, content) {
        const self = this;
        const sections = self.PageContent().split("\n~~~section-marker~~~\n");

        // expand as needed
        while (sections.length-1 < index) {
            sections.push("");
        }

        if (sections[index] !== content) {
            sections[index] = content;
            self.PageContent(sections.join("\n~~~section-marker~~~\n"));
        }
    };

    Page.prototype.handleClose = function(data, saveChanges) {
        const self = this;

        let callback = null;
        const obj = ko.unwrap(data);
        if (Object.prototype.hasOwnProperty.call(obj, "next")) {
            callback = obj.next;
        }
        if (saveChanges) {
            self.save(callback);
        } else if (callback && typeof callback === "function") {
            callback();
        }
    };

    Page.prototype.dispose = function() {
        const self = this;
        self.deleteDraft();
    };

    return {
        viewModel: Page,
        template: { require: "text!components/page/page.html" }
    };

});
