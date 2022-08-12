/**!
 * violetCMS – Plugins
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "knockout-mapping", "ajax", "utils"], function(ko, koMapping, ajax, ux) {
    "use strict";

    /**
     * The list of installed plugins
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Plugins() {

        const self = this;

        self.Plugins = ko.observableArray([]);


        /* ----- HELPER FUNCTIONS ----- */

        /**
         * progressbar for lengthy uploads
         */
        self.showUpload = ko.observable(false);
        self.showUploadProgress = ko.observable(0);


        /* ----- BINDING HANDLERS ----- */

        ko.bindingHandlers.fileSelect = {
            init: function(element, valueAccessor, allBindings, viewModel, bindingContext) {
                ko.applyBindingsToNode(element, {
                    event: { change: function() {
                        const uploadHandler = valueAccessor();
                        const fileList = element.files;
                        uploadHandler.call(self, fileList);
                    }}
                }, bindingContext);
            }
        };


        /* ----- INIT ----- */

        self.update();
    }

    Plugins.prototype.update = function() {
        const self = this;
        ajax.get("?q=plugins", function(data) {
            if (data) {

                /**
                 * Augment Plugin observable
                 *
                 * @member {observable} collapsed  Plugin info collapsed state
                 * @member {function}   showInfo   Toggle collapsed state
                 */
                const mapping = {
                    create: function(options) {
                        const plugin = function(pluginData) {
                            koMapping.fromJS(pluginData, {}, this);
                            this.collapsed = ko.observable(true);
                            this.showInfo = function() {
                                this.collapsed(!this.collapsed());
                            }.bind(this);
                        };
                        return new plugin(options.data);
                    }
                };
                koMapping.fromJS(data.Plugins, mapping, self.Plugins);
            }
        });
    };

    Plugins.prototype.install = function(fileList) {
        const self = this;
        if (fileList.length > 0) {
            self.showUpload(true);
            const data = {
                directory : "/plugins",
                fileList  : fileList
            };
            ajax.upload("plugins", data, self.showUploadProgress, function(response) {
                self.showUpload(false);
                const msg = response.Upload;
                if (msg.success) {
                    self.update();
                } else if (msg.error) {
                    ux.notify(msg.error, "error");
                }
            }, function(errMsg) {
                self.showUpload(false);
                ux.notify(errMsg, "error");
            });
        }
    };

    Plugins.prototype.dispose = function() {};

    return {
        viewModel: Plugins,
        template: { require: "text!components/plugins/plugins.html" }
    };

});
