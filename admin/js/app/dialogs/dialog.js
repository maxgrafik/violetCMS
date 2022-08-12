/**!
 * violetCMS – Dialogs
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "utils"], function(ko, ux) {
    "use strict";

    /**
     * All-purpose dialog handler
     *
     * @param {object}   params           The params object
     * @param {function} params.data      The data item from the parent view as observable (!) not the data
     * @param {function} params.onChange  Callback fires on every change
     * @param {function} params.onSubmit  Callback fires on submission
     */

    function Dialog(params) {

        const self = this;

        /* ----- VIEW MODEL ----- */

        self.selectedItem = params.data;

        self.onChange = params.onChange;
        self.onSubmit = params.onSubmit;

        if (typeof self.onChange === "function") {
            self.DirtyFlag = new ko.dirtyFlag(self.selectedItem);
            self.DirtyFlag.isDirty.subscribe(function() {
                if (self.onChange(self.selectedItem, self.DirtyFlag.getDirty())) {
                    self.DirtyFlag.setClean();
                }
            });
        }

    }

    Dialog.prototype.submit = function() {
        const self = this;

        // call onSubmit handler
        if (self.onSubmit && typeof self.onSubmit === "function") {
            self.onSubmit(self.selectedItem, true);
        }

        self.close();
    };

    Dialog.prototype.discard = function() {
        const self = this;

        // call onSubmit handler
        if (self.onSubmit && typeof self.onSubmit === "function") {
            self.onSubmit(self.selectedItem, false);
        }

        self.close();
    };

    Dialog.prototype.close = function() {
        const self = this;
        ux.hideModal(function() {
            self.selectedItem(null);
        });
    };

    /* ----- CLEAN UP ----- */

    Dialog.prototype.dispose = function() {
        const self = this;
        if (self.DirtyFlag) {
            self.DirtyFlag.dispose();
            self.DirtyFlag = null;
        }
    };

    return Dialog;
});
