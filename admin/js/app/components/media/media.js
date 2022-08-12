/**!
 * violetCMS – Media Management
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "knockout-mapping", "ajax", "rubberband", "utils", "text!components/media/media.json"], function(ko, koMapping, ajax, Rubberband, ux, MIMETypes) {
    "use strict";

    /**
     * Handles media management
     * Upload/Move/Rename/Delete files
     *
     * @param {object}     params         The params object
     * @param {object}     params.user    The user object ($root.User)
     * @param {object}     params.config  The config object ($root.Config)
     * @param {observable} params.request The request observable from admin
     */

    function Media() {

        const self = this;

        self.subscriptions = [];


        /* ----- VIEW MODELS ----- */

        self.MediaFiles = ko.observableArray([]);
        self.MIMETypes = JSON.parse(MIMETypes);


        /* ----- COMPUTED ----- */

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
         * The parent directory item
         * @todo this needs a better icon
         */
        self.BackButton = ko.pureComputed(function() {
            if (self.currentDirectory()) {
                const url = self.currentDirectory().split("/").slice(0, -1).join("/");
                if (url) {
                    return {
                        name     : ko.observable(url.split("/").splice(-1)),
                        type     : ko.observable("directory"),
                        url      : ko.observable(url),
                        selected : ko.observable(false)
                    };
                }
            }
            return null;
        }, self);

        /**
         * Breadcrumb navigation
         */
        self.Breadcrumbs = ko.pureComputed(function() {
            const breadcrumbs = [];
            self.currentDirectory().split("/").forEach(function(item) {
                if (item !== "") {
                    breadcrumbs.push({
                        name : item,
                        url  : "/"+breadcrumbs.map(x => x.name).concat([item]).join("/")
                    });
                }
            });
            return breadcrumbs;
        }, self);


        /* ----- HELPER FUNCTIONS ----- */

        /**
         * currentDirectory = The directory whose contents we're currently viewing
         * rootURL          = The root url to prepend to image (thumb) src
         */
        self.currentDirectory = ko.observable("");
        self.rootURL = ko.observable("");

        /**
         * progressbar for lengthy uploads
         */
        self.showUpload = ko.observable(false);
        self.showUploadProgress = ko.observable(0);

        self.enableDelete = ko.observable(false);
        self.confirmDelete = ko.observable(null);

        self.clearSelection = function() {
            ko.utils.arrayForEach(self.MediaFiles(), function(item) {
                item.selected(false);
            });
        };

        self.Rubberband = new Rubberband("#media", ".media", function(items) {
            self.enableDelete(false);
            ko.utils.arrayForEach(items, function(item) {
                const data = ko.dataFor(item);
                data.selected(true);
            });
        });


        /* ----- REGISTER DIALOGS ----- */

        if (!ko.components.isRegistered("deleteFile")) {
            ko.components.register("deleteFile", {
                viewModel: { require: "dialog/dialog" },
                template: { require: "text!dialog/file-delete.html" }
            });
        }


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

        ko.bindingHandlers.mouse = {
            init: function(element) {

                let holdTimer = null;
                let holdDelay = 800;

                let lastClick = 0;

                // @todo Holding down the mouse button to enter "delete mode"
                // is not really intuitive on Desktop

                function onMouseDown(event) {
                    const fig = event.target.closest("figure.media");
                    if (fig && event.button === 0) {
                        holdTimer = setTimeout(function() {
                            holdTimer = null;
                            self.enableDelete(true);
                        }, holdDelay);
                    }
                }

                function onMouseUp(event) {
                    if (holdTimer) {
                        clearTimeout(holdTimer);
                    }
                    const fig = event.target.closest("figure.media");
                    if (fig) {
                        if (self.enableDelete()) {
                            self.clearSelection();
                        } else {
                            const data = ko.dataFor(fig);
                            if (event.shiftKey) {
                                data.selected(true);
                            } else if (Date.now()-lastClick < 500) {
                                // double click
                                if (data.type() === "directory") {
                                    self.enableDelete(false);
                                    self.clearSelection();
                                    self.currentDirectory(ko.unwrap(data.url));
                                    self.update(ko.unwrap(data.url));
                                } else {
                                    const prop = data.type();
                                    if (prop.startsWith("audio") || prop.startsWith("image") || prop.startsWith("video")) {
                                        self.MediaPreview(data);
                                    }
                                }
                            } else {
                                self.clearSelection();
                                data.selected(true);
                            }
                            lastClick = Date.now();
                        }
                    } else {
                        self.enableDelete(false);
                        self.clearSelection();
                    }
                }

                element.addEventListener("mouseup", onMouseUp);
                element.addEventListener("mousedown", onMouseDown);
                element.addEventListener("mouseout", function() {
                    if (holdTimer) {
                        clearTimeout(holdTimer);
                    }
                });
                element.addEventListener("mousemove", function() {
                    if (holdTimer) {
                        clearTimeout(holdTimer);
                    }
                });
            }
        };

        ko.bindingHandlers.disableDrag = {
            update: function(element, valueAccessor) {
                const isEditing = ko.unwrap(valueAccessor());
                const preventClicks = function(event) { event.stopPropagation(); };
                self.Rubberband.enabled(!isEditing);
                if (isEditing) {
                    element.closest("figure").removeAttribute("draggable");
                    element.addEventListener("mousedown", preventClicks);
                } else {
                    element.closest("figure").setAttribute("draggable", "true");
                    element.removeEventListener("mousedown", preventClicks);
                }
            }
        };

        ko.bindingHandlers.drag = {
            init: function(element, valueAccessor, allBindings, viewModel) {
                let dragImg = null;
                const preloadDragImg = new Image();
                preloadDragImg.src = "img/mime-types/icon-multi.svg";

                element.setAttribute("draggable", "true");
                element.addEventListener("dragstart", function(event) {
                    viewModel.selected(true);
                    self.Rubberband.enabled(false);
                    event.stopPropagation();
                    event.dataTransfer.effectAllowed = "copyMove";

                    const items = [];
                    ko.utils.arrayForEach(self.MediaFiles(), function(MediaFile) {
                        if (MediaFile.selected()) {
                            items.push(MediaFile);
                        }
                    });

                    if (items.length > 1) {
                        dragImg = document.createElement("figure");
                        dragImg.classList.add("media");
                        dragImg.classList.add("dragimg");
                        dragImg.style.top = element.offsetTop + "px";
                        dragImg.style.left = element.offsetLeft + "px";
                        dragImg.innerHTML = "<img src='img/mime-types/icon-multi.svg'><figcaption>"+items.length+" items</figcaption>";
                        document.querySelector("#media").appendChild(dragImg);
                        event.dataTransfer.setDragImage(dragImg, 0, 0);
                    }

                    const uriList = [];
                    const textPlain = [];
                    ko.utils.arrayForEach(items, function(item) {
                        const proto = window.location.protocol;
                        const host = window.location.host;
                        const url = proto + "//" + host + self.rootURL() + item.url();
                        uriList.push(url);
                        textPlain.push(item.url());

                        // https://www.thecssninja.com/javascript/gmail-dragout
                        // but unfortunately we can't set multiple files at once
                        // doesn't work on safari either
                        if (items.length === 1) {
                            event.dataTransfer.setData("DownloadURL", item.type()+":"+item.name()+":"+url);
                        }
                    });

                    /**
                     * event.dataTransfer for "text/uri-list" seems broken on Chrome for Mac
                     * so we use "text/plain" when handling drag & drop
                     */

                    event.dataTransfer.setData("text/uri-list", uriList.join("\r\n"));
                    event.dataTransfer.setData("text/plain", textPlain.join("\r\n"));
                });
                element.addEventListener("dragend", function() {
                    if (dragImg) {
                        dragImg.parentElement.removeChild(dragImg);
                        dragImg = null;
                    }
                    self.Rubberband.enabled(true);
                    self.clearSelection();
                });
            }
        };

        ko.bindingHandlers.drop = {
            init: function(element, valueAccessor, allBindings, viewModel) {
                const valueUnwrapped = ko.unwrap(valueAccessor());
                const accept = valueUnwrapped.accept;
                const callback = valueUnwrapped.onDrop;
                element.addEventListener("dragover", function(event) {
                    event.preventDefault();
                    if (event.dataTransfer.types.includes(accept) && !element.classList.contains("acceptDrop")) {
                        element.classList.add("acceptDrop");
                    }
                });
                element.addEventListener("dragleave", function() {
                    element.classList.remove("acceptDrop");
                });
                element.addEventListener("drop", function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    event.dataTransfer.dropEffect = "move";
                    element.classList.remove("acceptDrop");
                    if (event.dataTransfer.types.includes(accept) && callback && typeof callback === "function") {
                        callback.call(viewModel, event);
                    }
                });
            }
        };

        /**
         * MediaPreview is the valueAccessor for the lightbox binding
         */
        self.MediaPreview = ko.observable(null);

        ko.bindingHandlers.lightbox = {
            update: function(element, valueAccessor) {
                const observable = valueAccessor();
                const item = ko.unwrap(observable);
                if (item !== null && typeof item === "object") {
                    ux.showLightbox(item.type(), self.rootURL() + item.url());
                }
            }
        };
        ko.virtualElements.allowedBindings.lightbox = true;


        /* ----- INIT ----- */

        self.update(self.currentDirectory());

    }

    Media.prototype.update = function(url) {
        const self = this;
        self.clearSelection();
        ko.utils.arrayForEach(self.subscriptions, function(item) {
            item.dispose();
        });
        ajax.get("?q=media&url="+url, function(data) {
            if (data) {
                self.currentDirectory(data.Media.currentURL);
                self.rootURL(data.Media.rootURL);

                // Augment the MediaFiles ViewModel
                /* eslint-disable-next-line no-inner-declarations */
                function MediaFile(dataVM) {
                    const vm = koMapping.fromJS(dataVM, {}, this);
                    vm.selected = ko.observable(false);
                    vm.editing = ko.observable(false);
                    vm.edit = function() { vm.editing(true); };
                    return vm;
                }
                const mapping = {
                    create: function(options) {
                        return new MediaFile(options.data);
                    }
                };

                koMapping.fromJS(data.Media.files, mapping, self.MediaFiles);
                self.MediaFiles.sort(function(item1, item2) {
                    if (item1.type() === "directory" && item2.type() !== "directory") { return -1; }
                    if (item1.type() !== "directory" && item2.type() === "directory") { return 1; }
                    return item1.name().toLowerCase() === item2.name().toLowerCase() ? 0 : (item1.name().toLowerCase() < item2.name().toLowerCase() ? -1 : 1);
                });

                ko.utils.arrayForEach(self.MediaFiles(), function(item) {
                    item.dirtyFlag = new ko.dirtyFlag(item.name);
                    self.subscriptions.push(item.dirtyFlag.isDirty.subscribe(self.renameFile.bind(self, item)));
                });
            }
        }, null);
    };

    Media.prototype.upload = function(fileList) {
        const self = this;
        if (fileList.length > 0) {
            self.showUpload(true);
            const data = {
                directory : self.currentDirectory(),
                fileList  : fileList
            };
            ajax.upload("media", data, self.showUploadProgress, function(response) {
                self.showUpload(false);
                const msg = response.Upload;
                if (msg.success) {
                    self.update(self.currentDirectory());
                } else if (msg.error) {
                    ux.notify(msg.error, "error");
                }
            }, function(errMsg) {
                self.showUpload(false);
                ux.notify(errMsg, "error");
            });
        }
    };

    Media.prototype.uploadFile = function(event) {
        const self = this;
        self.upload(event.dataTransfer.files);
    };

    Media.prototype.renameFile = function(file) {
        const self = this;
        if (file.dirtyFlag.isDirty()) {
            const data = {
                file : file.url(),
                name : file.name()
            };
            ajax.post("?q=media&action=rename", koMapping.toJSON(data), function() {
                self.update(self.currentDirectory());
            });
        }
    };

    /**
     * event.dataTransfer for "text/uri-list" seems broken on Chrome for Mac
     * so we use "text/plain" when handling drag & drop
     */

    Media.prototype.moveFile = function(event) {
        const context = ko.contextFor(event.target);
        const self = context.$component;
        const target = this.url();
        const files = event.dataTransfer.getData("text/plain").split("\r\n");
        const fileList = [];
        ko.utils.arrayForEach(files, function(file) {
            if (target !== file) {
                fileList.push(file);
            }
        });
        if (fileList.length) {
            const data = {
                fileList : fileList,
                target   : target
            };
            ajax.post("?q=media&action=move", koMapping.toJSON(data), function() {
                self.update(self.currentDirectory());
            });
        }
    };

    Media.prototype.getConfirmDelete = function(data, event) {
        const context = ko.contextFor(event.target);
        if (context) {
            const self = context.$component;
            self.confirmDelete(data);
        }
    };

    Media.prototype.deleteFile = function(file) {
        const self = this;
        const fileList = [];
        fileList.push(file().url());
        const data = {
            fileList : fileList
        };
        ajax.post("?q=media&action=delete", koMapping.toJSON(data), function() {
            self.update(self.currentDirectory());
        });
    };

    Media.prototype.newFolder = function() {
        const self = this;
        const data = {
            target: self.currentDirectory()
        };
        ajax.post("?q=media&action=createdir", koMapping.toJSON(data), function() {
            self.update(self.currentDirectory());
        });
    };

    Media.prototype.showDirectory = function(data, event) {
        const context = ko.contextFor(event.target);
        if (context) {
            const self = context.$component;
            self.currentDirectory(ko.unwrap(data.url));
            self.update(ko.unwrap(data.url));
        }
    };

    Media.prototype.dispose = function() {
        const self = this;
        ko.utils.arrayForEach(self.subscriptions, function(item) {
            item.dispose();
        });
    };

    return {
        viewModel: Media,
        template: { require: "text!components/media/media.html" }
    };

});
