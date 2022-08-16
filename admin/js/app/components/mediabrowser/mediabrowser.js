/**!
 * violetCMS – Basic Media Browser
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "knockout-mapping", "ajax", "text!components/media/media.json"], function(ko, koMapping, ajax, MIMETypes) {
    "use strict";

    /**
     * Simple media browser for "Create Link/Insert Image" dialogs
     *
     * @param {object}     params           The params object
     * @param {observable} params.url       The url to set when selecting a file
     * @param {observable} params.imgOnly   Only show images
     * @param {observable} params.onSubmit  Function to call on dblclick
     */

    function MediaBrowser(params) {

        const self = this;

        self.subscriptions = [];


        /* ----- VIEW MODELS ----- */

        self.MediaFiles = ko.observableArray([]);
        self.MIMETypes = JSON.parse(MIMETypes);


        /* ----- COMPUTED ----- */

        self.MediaFilesFiltered = ko.pureComputed(function() {
            return ko.utils.arrayFilter(self.MediaFiles(), function(item) {
                return params.imgOnly ? (item.type() === "directory" || item.type().startsWith("image")) : true;
            });
        });


        /**
         * File Icons or Thumbs
         */
        self.MediaIcon = function(data) {
            return ko.pureComputed(function() {
                const prop = data.type();
                if (Object.prototype.hasOwnProperty.call(self.MIMETypes, prop)) {
                    if (["image/jpeg", "image/png", "image/gif"].includes(prop)) {
                        const url = data.url().split("/");
                        const file = url.pop();
                        const thumb = url.join("/") + "/thumbs/" + file + ".jpg";
                        return self.rootURL() + thumb;
                    }
                    return self.MIMETypes[prop];
                }
                return self.MIMETypes["generic"];
            }, self);
        };

        /**
         * The "one-level-up" directory item
         * @todo this needs a better icon
         */
        self.BackButton = ko.pureComputed(function() {
            if (self.currentDirectory()) {
                const url = self.currentDirectory().split("/").slice(0, -1).join("/");
                if (url) {
                    return {
                        name : ko.observable(url.split("/").splice(-1)),
                        type : ko.observable("directory"),
                        url  : ko.observable(url)
                    };
                }
            }
            return null;
        }, self);


        /* ----- HELPER FUNCTIONS ----- */

        self.currentDirectory = ko.observable("");
        self.rootURL = ko.observable("");

        self.selected = ko.observable();
        self.subscriptions.push(self.selected.subscribe(function(item) {
            params.url && params.url(item ? item.url() : null);
        }, self));

        self.onSubmit = function() {
            if (params.onSubmit && typeof params.onSubmit === "function") {
                params.onSubmit();
            }
        };


        /* ----- INIT ----- */

        self.update(self.currentDirectory());
    }

    MediaBrowser.prototype.update = function(url) {
        const self = this;
        self.selected(null);
        ajax.get("?q=media&url="+url, function(data) {
            if (data) {
                self.currentDirectory(data.Media.currentURL);
                if (data.Media.rootURL === "/") {
                    self.rootURL("");
                } else {
                    self.rootURL(data.Media.rootURL);
                }

                koMapping.fromJS(data.Media.files, {}, self.MediaFiles);
                self.MediaFiles.sort(function(item1, item2) {
                    if (item1.type() === "directory" && item2.type() !== "directory") { return -1; }
                    if (item1.type() !== "directory" && item2.type() === "directory") { return 1; }
                    return item1.name().toLowerCase() === item2.name().toLowerCase() ? 0 : (item1.name().toLowerCase() < item2.name().toLowerCase() ? -1 : 1);
                });
            }
        }, null);
    };

    MediaBrowser.prototype.handleClick = function(data, event) {
        event.preventDefault();
        event.stopPropagation();
        const context = ko.contextFor(event.target);
        if (context) {
            const self = context.$component;
            if (data.type() === "directory") {
                self.selected(null);
                self.currentDirectory(ko.unwrap(data.url));
                self.update(ko.unwrap(data.url));
            } else {
                self.selected(data);
            }
        }
    };

    MediaBrowser.prototype.dispose = function() {
        const self = this;
        ko.utils.arrayForEach(self.subscriptions, function(item) {
            item.dispose();
        });
    };

    return {
        viewModel: MediaBrowser,
        template: { require: "text!components/mediabrowser/mediabrowser.html" }
    };

});
